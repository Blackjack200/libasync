<?php

namespace libasync\await;

use Closure;
use Generator;
use libasync\AsyncTimings;
use libasync\exception\ExecutionException;
use libasync\exception\ExecutionExceptionWrapper;
use libasync\exception\TimeoutException;
use pocketmine\Server;
use pocketmine\thread\ThreadCrashInfoFrame;
use pocketmine\timings\TimingsHandler;
use pocketmine\utils\Filesystem;
use pocketmine\utils\Utils as PMMPUtils;

class Coroutine {
	/** @var string[] */
	private array $callTrace;
	private string $name;
	/** @var Closure():bool[] */
	private array $trap = [];
	private array $defer = [];

	public static ?self $RUNNING = null;
	private float $startTime = PHP_FLOAT_MAX;
	public float $timeout = PHP_FLOAT_MAX;

	public static array $joinedCoroutine = [];

	public function __construct(
		private readonly Generator $generator,
		private readonly ?Closure $errorHandler,
		private readonly bool     $joined
	) {
		//skip __construct frame
		$this->callTrace = PMMPUtils::printableCurrentTrace(3);
		if (TimingsHandler::isEnabled()) {
			$trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
			if (isset($trace[3])) {
				$caller = $trace[3];
				$this->name = Filesystem::cleanPath($caller['file']) . '#L' . $caller['line'];
			} else {
				$this->name = 'UNKNOWN';
			}
		} else {
			$this->name = 'UNKNOWN';
		}
		if ($this->joined) {
			self::$joinedCoroutine[spl_object_id($this)] = $this;
		}
	}

	public function getName() : string { return $this->name; }

	public function addTrap(Closure $trap) : void { $this->trap[] = $trap; }

	public function addDefer(Closure $defer) : void {
		$this->defer[] = $defer;
	}

	public function register(EventLoop $loop) : void {
		$timings = AsyncTimings::getByName($this->name);
		$resumeTimings = AsyncTimings::getResumeByName($this->name);
		$this->startTime = microtime(true);
		$loop->add(function($break, $changeToWakeupMode) use ($resumeTimings, $timings) : void {
			$break = function() use ($break) {
				unset(self::$joinedCoroutine[spl_object_id($this)]);
				$break();
				foreach ($this->defer as $defer) {
					$defer();
				}
			};
			try {
				self::$RUNNING = $this;
				$timings->startTiming();

				if ($this->runInternal($timings, $resumeTimings, $changeToWakeupMode)) {
					$break();
					return;
				}
			} catch (\Throwable $thr) {
				$break();
				if ($this->errorHandler !== null) {
					($this->errorHandler)($thr);
				} else {
					self::crash($thr, $this->callTrace);
				}
			} finally {
				self::$RUNNING = null;
				$timings->stopTiming();
			}
		});
	}

	private static function crash(\Throwable $thr, array $callTrace) : void {
		$x = [];
		foreach ($callTrace as $xb => $ttr) {
			$x[$xb] = new ThreadCrashInfoFrame($ttr, 'unknown', 0);
		}
		if ($thr instanceof ExecutionException) {
			$thr->printWithCallTrace(\GlobalLogger::get());
			$wrapper = $thr->getWrapper();
		} else {
			\GlobalLogger::get()->logException($thr);
			$wrapper = ExecutionExceptionWrapper::wrap($thr);
		}

		global $lastExceptionError;
		$lastExceptionError = [
			'type' => $wrapper->getClass(),
			'message' => $wrapper->getMessage(),
			'fullFile' => $wrapper->getFile(),
			'file' => Filesystem::cleanPath($wrapper->getFile()),
			'line' => $wrapper->getLine(),
			'trace' => $x,
			'thread' => 'Coroutine',
		];
		Server::getInstance()->crashDump();
	}

	public function getGenerator() : Generator { return $this->generator; }

	/**
	 * @return bool should break
	 */
	private function runInternal(TimingsHandler $timings, TimingsHandler $resumeTimings, Closure $changeToWakeupMode) : bool {
		$gen = $this->generator;
		if (($elapsed = microtime(true) - $this->startTime) >= $this->timeout) {
			$timeout = new TimeoutException("Coroutine timed out, elapsed=$elapsed, timeout=$this->timeout");
			$gen->throw(new ExecutionException(ExecutionExceptionWrapper::wrap($timeout), $this->callTrace));
			return true;
		}
        if (!$gen->valid() || (function () {
                for ($i = count($this->trap) - 1; $i >= 0; $i--) {
                    if (!$this->trap[$i]()) {
                        return true;
                    }
                }
                return false;
            })()
		) {
			$timings->stopTiming();
			return true;
		}
		$d = $gen->current();
		switch ($d) {
			case AwaitSignal::SIG_NOTIFIED:
				$gen->next();
				$setNotifier = $gen->current();
				$setNotifier($changeToWakeupMode());
				break;
			case AwaitSignal::SIG_WAIT:
				break;
			case AwaitSignal::SIG_EXCEPTION:
				$gen->next();
				[$callTrace, $exp] = $gen->current();
				if ($exp !== null) {
					$gen->throw(new ExecutionException($exp, $callTrace));
				}
				break;
			case AwaitSignal::SIG_TRAP:
				$gen->next();
				$this->trap[] = $gen->current();
				break;
			case AwaitSignal::SIG_FINISH:
			case AwaitSignal::SIG_INTERRUPT:
				return true;
		}
		try {
			$resumeTimings->time($gen->next(...));
		} catch (\Throwable $thr) {
			if (!($thr instanceof ExecutionException)) {
				$thr = new ExecutionException(ExecutionExceptionWrapper::wrap($thr), $this->callTrace);
			}
			throw $thr;
		}
		return false;
	}
}
<?php

namespace libasync\await;

use Closure;
use Generator;
use libasync\AsyncTimings;
use libasync\exception\ExecutionException;
use libasync\exception\ExecutionExceptionWrapper;
use pocketmine\Server;
use pocketmine\thread\ThreadCrashInfoFrame;
use pocketmine\timings\TimingsHandler;
use pocketmine\utils\Filesystem;
use pocketmine\utils\Utils as PMMPUtils;

class Coroutine {
	/** @var string[] */
	private array $callTrace;
	private string $name;

	public function __construct(
		private readonly Generator $generator,
		private readonly Closure   $errorHandler
	) {
		//skip __construct frame
		$this->callTrace = PMMPUtils::printableCurrentTrace(1);
		if (TimingsHandler::isEnabled()) {
			$trace = debug_backtrace();
			if (isset($trace[3])) {
				$caller = $trace[3];
				$this->name = Filesystem::cleanPath($caller['file']) . '#L' . $caller['line'];
			} else {
				$this->name = 'UNKNOWN';
			}
		} else {
			$this->name = 'UNKNOWN';
		}
	}

	public function getName() : string { return $this->name; }

	public function register(EventLoop $loop) : void {
		$timings = AsyncTimings::getByName($this->name);
		$resumeTimings = AsyncTimings::getResumeByName($this->name);
		$loop->add(function($break) use ($resumeTimings, $timings) : void {
			$gen = $this->generator;
			//var_dump($this->getName());
			$timings->startTiming();
			try {
				if (!$gen->valid()) {
					$timings->stopTiming();
					return;
				}
				$d = $gen->current();
				switch ($d) {
					case AwaitSignal::SIG_WAIT:
						break;
					case AwaitSignal::SIG_EXCEPTION:
						$gen->next();
						[$callTrace, $exp] = $gen->current();
						if ($exp !== null) {
							$gen->throw(new ExecutionException($exp, $callTrace));
						}
						break;
					case AwaitSignal::SIG_FINISH:
					case AwaitSignal::SIG_INTERRUPT:
						$break();
						break;
				}
				$resumeTimings->time($gen->next(...));
			} catch (\Throwable $thr) {
				$break();
				if ($this->errorHandler !== null) {
					($this->errorHandler)(new ExecutionException(ExecutionExceptionWrapper::wrap($thr), $this->callTrace));
				} else {
					self::crash($thr, $this->callTrace);
				}
			} finally {
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
}
<?php
declare(strict_types=1);

namespace libasync\await;

use Closure;
use Generator;
use GlobalLogger;
use libasync\AsyncTimings;
use libasync\exception\ExecutionException;
use libasync\exception\ExecutionExceptionWrapper;
use libasync\exception\TimeoutException;
use pocketmine\Server;
use pocketmine\thread\ThreadCrashInfoFrame;
use pocketmine\timings\TimingsHandler;
use pocketmine\utils\Filesystem;
use pocketmine\utils\Utils as PMMPUtils;
use RuntimeException;
use Throwable;

class Coroutine {
	private const SKIP_FRAMES = 3;
	public static ?self $RUNNING = null;
	/** @var array<int,self> */
	public static array $joinedCoroutine = [];
	private readonly array $callTrace;
	private readonly string $name;
	/** @var (Closure():bool)[] */
	private array $trapHandlers = [];
	/** @var (Closure():void)[] */
	private array $deferHandlers = [];
	private EventLoopTask $task;

	public function __construct(
		private readonly Generator $generator,
		private readonly ?Closure $errorHandler,
		private readonly bool     $joined
	) {
		$this->callTrace = PMMPUtils::printableCurrentTrace(self::SKIP_FRAMES);
		$this->name = $this->determineCallerName();

		if ($this->joined) {
			self::$joinedCoroutine[spl_object_id($this)] = $this;
		}
	}

	private function determineCallerName() : string {
		if (!TimingsHandler::isEnabled()) {
			return 'Unknown';
		}

		$trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, self::SKIP_FRAMES + 1);
		$caller = $trace[self::SKIP_FRAMES] ?? null;

		if ($caller === null) {
			return 'Unknown';
		}

		return Filesystem::cleanPath($caller['file']) . '#L' . $caller['line'];
	}

	public function getName() : string { return $this->name; }

	/**
	 * @param Closure():bool $trap
	 */
	public function addTrap(Closure $trap) : void { $this->trapHandlers[] = $trap; }

	/**
	 * @param Closure():void $defer
	 */
	public function addDefer(Closure $defer) : void { $this->deferHandlers[] = $defer; }

	public function register(EventLoop $loop) : void {
		$timings = AsyncTimings::getByName($this->name);
		$resumeTimings = AsyncTimings::getResumeByName($this->name);

		$this->task = $loop->add(function(Closure $break, Closure $wakeupMode) use ($timings, $resumeTimings) : void {
			try {
				self::$RUNNING = $this;
				$timings->startTiming();

				if ($this->executeInternal($timings, $resumeTimings, $wakeupMode)) {
					$this->terminate($break);
				}
			} catch (Throwable $thr) {
				$this->terminate($break);
				$this->handleException($thr);
			} finally {
				self::$RUNNING = null;
				$timings->stopTiming();
			}
		});

		$this->task->setOnTimeout($this->handleTimeout(...));
	}

	private function executeInternal(TimingsHandler $timings, TimingsHandler $resumeTimings, Closure $wakeupMode) : bool {
		$gen = $this->generator;

		if (!$gen->valid() || !$this->processTraps()) {
			$timings->stopTiming();
			return true;
		}

		$signal = $gen->current();

		match ($signal) {
			AwaitSignal::SIG_NOTIFIED => $this->handleNotifiedSignal($gen, $wakeupMode),
			AwaitSignal::SIG_WAIT => null,
			AwaitSignal::SIG_EXCEPTION => $this->handleExceptionSignal($gen),
			AwaitSignal::SIG_TRAP => $this->handleTrapSignal($gen),
			AwaitSignal::SIG_FINISH, AwaitSignal::SIG_INTERRUPT => true,
			default => throw new RuntimeException("Unsupported signal: " . var_export($signal, true))
		};

		$resumeTimings->time(fn() => $gen->next());
		return false;
	}

	private function processTraps() : bool {
		foreach (array_reverse($this->trapHandlers) as $trap) {
			if (!$trap()) {
				return false;
			}
		}

		return true;
	}

	private function handleNotifiedSignal(Generator $gen, Closure $wakeupMode) : void {
		$gen->next();
		$notifier = $gen->current();
		$notifier($wakeupMode());
	}

	private function handleExceptionSignal(Generator $gen) : void {
		$gen->next();
		[$callTrace, $exception] = $gen->current();

		if ($exception !== null) {
			$gen->throw(new ExecutionException($exception, $callTrace));
		}
	}

	private function handleTrapSignal(Generator $gen) : void {
		$gen->next();
		$this->trapHandlers[] = $gen->current();
	}

	private function terminate(Closure $break) : void {
		unset(self::$joinedCoroutine[spl_object_id($this)]);

		foreach ($this->deferHandlers as $defer) {
			$defer();
		}

		$break();
	}

	private function handleException(Throwable $thr) : void {
		if ($this->errorHandler !== null) {
			($this->errorHandler)($thr);
		} else {
			self::logCrash($thr, $this->callTrace);
		}
	}

	private static function logCrash(Throwable $thr, array $callTrace) : void {
		$x = array_map(static fn($ttr) => new ThreadCrashInfoFrame($ttr, 'unknown', 0), $callTrace);
		if ($thr instanceof ExecutionException) {
			$thr->printWithCallTrace(GlobalLogger::get());
			$wrapper = $thr->getWrapper();
		} else {
			GlobalLogger::get()->logException($thr);
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

	public function getTask() : EventLoopTask { return $this->task; }

	private function handleTimeout(float $exceed) : void {
		unset(self::$joinedCoroutine[spl_object_id($this)]);

		$timeoutException = new TimeoutException("Coroutine timed out, exceed=$exceed");
		$executionException = new ExecutionException(ExecutionExceptionWrapper::wrap($timeoutException), $this->callTrace);

		$this->generator->throw($executionException);
	}
}

<?php

namespace libasync\await;

use Closure;
use DaveRandom\CallbackValidator\CallbackType;
use DaveRandom\CallbackValidator\ReturnType;
use Generator;
use GlobalLogger;
use libasync\AsyncTimings;
use libasync\exception\ExecutionException;
use libasync\exception\ExecutionExceptionWrapper;
use libasync\global\GlobalAsyncRuntime;
use libasync\runtime\AsyncExecutionEnvironment;
use libasync\runtime\AsyncRuntime;
use pocketmine\Server;
use pocketmine\thread\ThreadCrashInfoFrame;
use pocketmine\utils\Filesystem;
use pocketmine\utils\Utils as PMMPUtils;
use Throwable;
use const bootstrap\PRODUCTION;

final class Await {
	private function __construct() { }

	/** @return Generator<void,AwaitSignal,void,void> */
	public static function suspend() : Generator {
		yield AwaitSignal::SIG_WAIT;
	}

	/** @return Generator<void,AwaitSignal,void,void> */
	public static function delay(int $sec) : Generator {
		yield from self::udelay($sec * 1000);
	}

	/** @return Generator<void,AwaitSignal,void,void> */
	public static function udelay(int $microseconds) : Generator {
		yield from self::ndelay($microseconds * 1000 * 1000);
	}

	/** @return Generator<void,AwaitSignal,void,void> */
	public static function ndelay(int $nanoseconds) : Generator {
		$targetTime = hrtime(true) + $nanoseconds;
		while (((float) hrtime(true)) < $targetTime) {
			yield AwaitSignal::SIG_WAIT;
		}
	}

	/**
	 * @param Closure(Closure $cancel):void $do
	 */
	public static function tick(Closure $do, int $tick, int $times) : Generator {
		$c = true;
		$cancel = static function() use (&$c) { $c = false; };
		while ($times-- > 0 && $c) {
			yield from self::udelay($tick * (1000 / 20));
			$do($cancel);
		}
	}

	public static function interrupt() : Generator {
		yield AwaitSignal::SIG_INTERRUPT;
	}

	/**
	 * @template T
	 * @param Closure():T $do
	 * @return Generator<void,AwaitSignal|mixed,void,T>|T
	 * @throws ExecutionException
	 */
	public static function threadify(Closure $do, ?AsyncRuntime $runtime = null, ?AsyncExecutionEnvironment $env = null) {
		$runtime ??= GlobalAsyncRuntime::gerThreadedRuntime();

		$rec = $runtime->runAsync($do, $env);

		yield AwaitSignal::SIG_SET_RECEIPT;
		yield $rec;

		yield from $rec->yieldWait();

		yield AwaitSignal::SIG_EXCEPTION;
		yield [$rec->getCallTrace(), $rec->getError()];

		return $rec->getResult();
	}

	/**
	 * @internal
	 */
	private static function wrapCoroutine(Closure $coroutineBody, Closure $errorHandler, ?EventLoop $loop = null) : void {
		$callTrace = PMMPUtils::printableCurrentTrace();
		$loop ??= GlobalAsyncRuntime::getLoop();
		if (!PRODUCTION) {
			PMMPUtils::validateCallableSignature(new CallbackType(new ReturnType(),), $coroutineBody);
		}
		$name = PMMPUtils::getNiceClosureName($coroutineBody);
		$coroutine = static function() use ($coroutineBody) : Generator {
			//this wait make all error after start.
			yield AwaitSignal::SIG_WAIT;
			$body = $coroutineBody();
			if (is_iterable($body)) {
				yield from $body;
			}
			yield AwaitSignal::SIG_FINISH;
		};
		self::registerCoroutineScheduler($name, $coroutine(), $loop, $callTrace, $errorHandler);
	}

	public static function do(Closure|Generator $block, ?EventLoop $loop = null) : AwaitResult {
		if ($block instanceof Generator) {
			$block = static fn() => $block;
		}
		return new AwaitResult(static fn(Closure $errorHandler) => self::wrapCoroutine($block, $errorHandler, $loop));
	}

	private static function registerCoroutineScheduler(string $name, Generator $coroutine, EventLoop $loop, array $callTrace, ?Closure $errorHandler = null) : void {
		$timings = AsyncTimings::getByName($name);
		$resumeTimings = AsyncTimings::getResumeByName($name);
		$loop->add(static function($break) use ($coroutine, $resumeTimings, $timings, $errorHandler, $callTrace) : void {
			if (!$coroutine->valid()) {
				return;
			}
			$timings->startTiming();
			try {
				try {
					$d = $coroutine->current();
					switch ($d) {
						case AwaitSignal::SIG_WAIT:
							break;
						case AwaitSignal::SIG_EXCEPTION:
							$coroutine->next();
							[$callTrace, $exp] = $coroutine->current();
							if ($exp !== null) {
								$coroutine->throw(new ExecutionException($exp, $callTrace));
							}
							break;
						case AwaitSignal::SIG_FINISH:
						case AwaitSignal::SIG_INTERRUPT:
							$break();
							break;
					}
					$resumeTimings->time($coroutine->next(...));
				} catch (Throwable $thr) {
					$break();
					if ($errorHandler !== null) {
						$errorHandler($thr);
					} else {
						throw $thr;
					}
				}
			} catch (Throwable $thr) {
				$break();
				self::crash($thr, $callTrace);
			}
			$timings->stopTiming();
		});
	}

	private static function crash(Throwable $thr, array $callTrace) : void {
		$x = [];
		foreach ($callTrace as $xb => $ttr) {
			$x[$xb] = new ThreadCrashInfoFrame($ttr, "unknown", 0);
		}
		if ($thr instanceof ExecutionException) {
			$thr->printWithCallTrace(GlobalLogger::get());
			$wrapper = $thr->getWrapper();
		} else {
			GlobalLogger::get()->logException($thr);
			$wrapper = ExecutionExceptionWrapper::wrap($thr);
		}

		global $lastExceptionError;
		$lastExceptionError = [
			"type" => $wrapper->getClass(),
			"message" => $wrapper->getMessage(),
			"fullFile" => $wrapper->getFile(),
			"file" => Filesystem::cleanPath($wrapper->getFile()),
			"line" => $wrapper->getLine(),
			"trace" => $x,
			"thread" => "Coroutine",
		];
		Server::getInstance()->crashDump();
	}
}
<?php

namespace libasync\await;

use Closure;
use DaveRandom\CallbackValidator\CallbackType;
use DaveRandom\CallbackValidator\ReturnType;
use Fiber;
use GlobalLogger;
use libasync\exception\ExecutionException;
use libasync\exception\ExecutionExceptionWrapper;
use libasync\future\Future;
use libasync\global\GlobalAsyncRuntime;
use libasync\runtime\AsyncExecutionEnvironment;
use libasync\runtime\AsyncExecutionReceipt;
use libasync\runtime\AsyncRuntime;
use pocketmine\Server;
use pocketmine\thread\ThreadCrashInfoFrame;
use pocketmine\utils\Filesystem;
use pocketmine\utils\Utils as PMMPUtils;
use RuntimeException;
use Throwable;
use const bootstrap\PRODUCTION;

final class Await {
	private function __construct() { }

	public static function sleep(int $sec) : void {
		self::usleep($sec * 1000);
	}

	public static function usleep(int $microseconds) : void {
		self::nsleep($microseconds * 1000 * 1000);
	}

	public static function nsleep(int $nanoseconds) : void {
		$targetTime = hrtime(true) + $nanoseconds;
		while (((float) hrtime(true)) < $targetTime) {
			self::suspend(AwaitSignal::SIG_WAIT);
		}
	}

	public static function tick(Closure $do, int $tick, int $times) : void {
		$c = true;
		$cancel = static function() use (&$c) { $c = false; };
		while ($times-- > 0 && $c) {
			self::usleep($tick * (1000 / 20));
			$do($cancel);
		}
	}

	public static function interrupt() : void {
		while (true) {
			self::suspend(AwaitSignal::SIG_WAIT);
		}
	}

	/**
	 * @template T
	 * @param Closure():T $do
	 * @return T
	 * @throws ExecutionException
	 */
	public static function threadify(Closure $do, ?AsyncRuntime $runtime = null, ?AsyncExecutionEnvironment $env = null) {
		$runtime ??= GlobalAsyncRuntime::gerThreadedRuntime();

		$rec = $runtime->runAsync($do, $env);

		self::suspend(AwaitSignal::SIG_SET_RECEIPT);
		self::suspend($rec);

		$rec->suspendWait();

		self::suspend(AwaitSignal::SIG_EXCEPTION);
		self::suspend($rec->getCallTrace());
		self::suspend($rec->getError());
		return $rec->getResult();
	}

	/**
	 * @internal
	 */
	private static function wrapCoroutine(Closure $coroutineFunc, ?EventLoop $loop = null) : void {
		$callTrace = PMMPUtils::printableCurrentTrace();
		$loop ??= GlobalAsyncRuntime::getLoop();
		if (!PRODUCTION) {
			PMMPUtils::validateCallableSignature(new CallbackType(new ReturnType(),), $coroutineFunc);
		}
		$coroutine = new Fiber(static function() use ($coroutineFunc) : void {
			self::suspend(AwaitSignal::SIG_WAIT);
			$coroutineFunc();
			self::suspend(AwaitSignal::SIG_FINISH);
		});
		$coroutine->start();
		self::registerCoroutineScheduler($coroutine, $loop, $callTrace);
	}


	public static function do(Closure $block, ?EventLoop $loop = null) : AwaitResult {
		return new AwaitResult(static fn() => self::wrapCoroutine($block, $loop));
	}

	public static function future(Closure $do) : Future {
		$callTrace = PMMPUtils::printableCurrentTrace();
		$loop = new EventLoop();
		if (!PRODUCTION) {
			PMMPUtils::validateCallableSignature(new CallbackType(new ReturnType(),), $do);
		}
		$fiber = new Fiber(static function() use ($do) : void {
			self::suspend(AwaitSignal::SIG_WAIT);
			$do();
			self::suspend(AwaitSignal::SIG_FINISH);
		});
		$fiber->start();
		$fiber->resume();
		$receipt = $fiber->resume();
		assert($receipt instanceof AsyncExecutionReceipt);
		self::registerCoroutineScheduler($fiber, $loop, $callTrace);
		while ($loop->busy()) {
			$loop->poll(50);
			usleep(50);
		}
		return new Future($receipt);
	}

	public static function suspend(...$args) {
		if (Fiber::getCurrent() === null) {
			throw new RuntimeException('Cannot call async function outside of sync context');
		}
		return Fiber::suspend(...$args);
	}

	private static function registerCoroutineScheduler(Fiber $fiber, EventLoop $loop, array $callTrace) : void {
		$loop->add(static function($break) use ($callTrace, $fiber) : void {
			for ($i = 0; $i < 2; $i++) {
				try {
					if (!$fiber->isSuspended()) {
						continue;
					}
					$d = $fiber->resume();
					switch ($d) {
						case AwaitSignal::SIG_SET_TRACE:
							$fiber->resume($callTrace);
							break;
						case AwaitSignal::SIG_WAIT:
							break;
						case AwaitSignal::SIG_EXCEPTION:
							$callTrace = $fiber->resume();
							$exp = $fiber->resume();
							if ($exp !== null) {
								$fiber->throw(new ExecutionException($exp, $callTrace));
							}
							break;
						case AwaitSignal::SIG_FINISH:
						case AwaitSignal::SIG_INTERRUPT:
							$break();
							break 2;
					}
				} catch (ExecutionException $thr) {
					$thr->printWithCallTrace(GlobalLogger::get());
					global $lastExceptionError, $lastError;
					$wrapper = $thr->getWrapper();
					$x = [];
					foreach ($wrapper->getTrace() as $xb => $ttr) {
						$x[$xb] = new ThreadCrashInfoFrame($ttr, "unknown", 0);
					}
					$lastError = $lastError = [
						"type" => $wrapper->getClass(),
						"message" => $wrapper->getMessage(),
						"fullFile" => $wrapper->getFile(),
						"file" => Filesystem::cleanPath($wrapper->getFile()),
						"line" => $wrapper->getLine(),
						"trace" => $x,
						"thread" => "Coroutine",
					];
					$lastExceptionError = $lastError;
					Server::getInstance()->crashDump();
				} catch (Throwable $thr) {
					ExecutionExceptionWrapper::wrap($thr)->printWithCallTrace($callTrace);
					throw $thr;
				}
			}
		});
	}
}
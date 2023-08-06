<?php

namespace libasync\await;

use Closure;
use DaveRandom\CallbackValidator\CallbackType;
use DaveRandom\CallbackValidator\ReturnType;
use libasync\exception\ExecutionException;
use libasync\exception\ExecutionExceptionWrapper;
use libasync\global\GlobalRuntime;
use libasync\runtime\AsyncRuntime;
use pocketmine\utils\Utils as PMMPUtils;
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
		while (hrtime(true) < $targetTime) {
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
	 * @param Closure(...$args):T $do
	 * @return T
	 * @throws \libasync\exception\ExecutionException
	 */
	public static function async(Closure $do, ?AsyncRuntime $runtime = null, ?Closure $extraArgPrepareFunc = null, ?Closure $extraArgDestroyFunc = null) {
		$runtime ??= GlobalRuntime::getRuntime();

		$rec = $runtime->runAsync($do, $extraArgPrepareFunc, $extraArgDestroyFunc);
		$rec->suspendWait();

		self::suspend(AwaitSignal::SIG_EXCEPTION);
		self::suspend($rec->getCallTrace());
		self::suspend($rec->getError());
		return $rec->getResult();
	}

	/**
	 * @internal
	 */
	private static function sync(Closure $do, ?EventLoop $loop = null) : void {
		$callTrace = PMMPUtils::printableCurrentTrace();
		$loop ??= GlobalRuntime::getLoop();
		if (!PRODUCTION) {
			PMMPUtils::validateCallableSignature(new CallbackType(new ReturnType(),), $do);
		}
		$fiber = new \Fiber(static function() use ($do) : void {
			self::suspend(AwaitSignal::SIG_WAIT);
			$do();
			self::suspend(AwaitSignal::SIG_FINISH);
		});
		$fiber->start();
		$loop->add(static function($unsubscribe) use ($callTrace, $fiber) : void {
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
							$unsubscribe();
							break 2;
					}
				} catch (ExecutionException $thr) {
					$thr->printWithCallTrace(\GlobalLogger::get());
					throw new \RuntimeException('async execution error');
				} catch (\Throwable $thr) {
					ExecutionExceptionWrapper::wrap($thr)->printWithCallTrace($callTrace);
					throw $thr;
				}
			}
		});
	}


	public static function do(Closure $do, ?EventLoop $loop = null) : AwaitResult {
		$func = static function(Closure $d) use ($do) : void {
			try {
				$do();
			} catch (\Throwable $thr) {
				$d($thr);
			}
		};
		return new AwaitResult($func, static fn(Closure $dd) => self::sync($dd, $loop));
	}

	public static function suspend(...$args) {
		if (\Fiber::getCurrent() === null) {
			throw new \RuntimeException('Cannot call async function outside of sync context');
		}
		return \Fiber::suspend(...$args);
	}
}
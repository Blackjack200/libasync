<?php

namespace libasync\await;

use Closure;
use DaveRandom\CallbackValidator\CallbackType;
use DaveRandom\CallbackValidator\ReturnType;
use libasync\exception\AsyncExceptionWrapped;
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
			\Fiber::suspend(AwaitSignal::SIG_WAIT);
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
			\Fiber::suspend(AwaitSignal::SIG_WAIT);
		}
	}

	/**
	 * @template T
	 * @param Closure(...$args):T $do
	 * @return T
	 * @throws \libasync\exception\AsyncExceptionWrapped
	 */
	public static function fiberAsync(Closure $do, ?AsyncRuntime $runtime = null, ?Closure $extraArgPrepareFunc = null, ?Closure $extraArgDestroyFunc = null) {
		$runtime ??= GlobalRuntime::getRuntime();

		$rec = $runtime->runAsync($do, $extraArgPrepareFunc, $extraArgDestroyFunc);
		$rec->suspendWait();

		\Fiber::suspend(AwaitSignal::SIG_EXCEPTION);
		\Fiber::suspend($rec->getCallTrace());
		\Fiber::suspend($rec->getError());
		return $rec->getResult();
	}

	public static function fiberAsync2(Closure $do, ?AsyncRuntime $runtime = null, ?Closure $extraArgPrepareFunc = null, ?Closure $extraArgDestroyFunc = null) {
		return self::fiberAsync($do, $runtime, $extraArgPrepareFunc, $extraArgDestroyFunc);
	}

	public static function fiberSync2(Closure $do, ?EventLoop $loop = null) : void {
		self::fiberSync($do, $loop);
	}

	/**
	 * @internal
	 */
	public static function fiberSync(Closure $do, ?EventLoop $loop = null) : void {
		$callTrace = PMMPUtils::printableCurrentTrace();
		$loop ??= GlobalRuntime::getLoop();
		if (!PRODUCTION) {
			PMMPUtils::validateCallableSignature(new CallbackType(new ReturnType(),), $do);
		}
		$fiber = new \Fiber(static function() use ($do) : void {
			$do();
			\Fiber::suspend(AwaitSignal::SIG_FINISH);
		});
		$fiber->start();
		$loop->add(static function($unsubscribe) use ($callTrace, $fiber) : void {
			for ($i = 0; $i < 2; $i++) {
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
							$fiber->throw(new AsyncExceptionWrapped($exp, $callTrace));
						}
						break;
					case AwaitSignal::SIG_FINISH:
					case AwaitSignal::SIG_INTERRUPT:
						$unsubscribe();
						break 2;
				}
			}
		});
	}


	public static function do(Closure $do, ?EventLoop $loop = null) : AwaitResult {
		$func = static function(Closure $d) use ($do) : void {
			try {
				$d();
			} catch (\Throwable $thr) {
				$do($thr);
			}
		};
		return new AwaitResult($func, static fn(Closure $do) => self::fiberSync($do, $loop));
	}
}
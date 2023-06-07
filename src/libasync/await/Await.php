<?php

namespace libasync\await;

use Closure;
use Generator;
use libasync\global\GlobalRuntime;
use libasync\runtime\AsyncRuntime;
use libasync\utils\Utils;

class Await {
	public function __construct() { }

	/**
	 * @template T
	 * @param Closure():T $do
	 * @return T
	 * @throws \libasync\exception\AsyncExecutionException
	 */
	public static function async(Closure $do, ?AsyncRuntime $runtime = null, ?Closure $extraArgPrepareFunc = null, ?Closure $extraArgDestroyFunc = null, ?EventLoop $loop = null) {
		if ($loop === null) {
			$loop = GlobalRuntime::getLoop();
		}
		if ($runtime === null) {
			$runtime = GlobalRuntime::getRuntime();
		}
		$reci = $runtime->runAsync($do, $extraArgPrepareFunc, $extraArgDestroyFunc);

		$loop->add(static function($unsubscribe) use ($reci) : void {
			if ($reci->isFinished()) {
				$unsubscribe();
			}
		});
		yield from $reci->awaitFinish();
		if ($reci->getError() !== null) {
			throw new \RuntimeException(Utils::printPromiseExceptionMessage($reci->getError()));
		}
		return $reci->getResult();
	}

	public static function sleep(int $sec) : Generator {
		yield from self::usleep($sec * 1000);
	}

	public static function usleep(int $microseconds) : Generator {
		yield from self::nsleep($microseconds * 1000 * 1000);
	}

	public static function nsleep(int $nanoseconds) : Generator {
		$targetTime = hrtime(true) + $nanoseconds;
		while (hrtime(true) < $targetTime) {
			yield AwaitSignal::SIG_WAIT;
		}
	}

	public static function interrupt() : Generator {
		while (true) {
			yield AwaitSignal::SIG_INTERRUPT;
		}
	}

	public static function do(callable $do, ?EventLoop $loop = null) {
		self::sync($do, $loop);
	}

	public static function sync(callable $do, ?EventLoop $loop = null) {
		if ($loop === null) {
			$loop = GlobalRuntime::getLoop();
		}
		$aa = function() use ($do) {
			$result = $do();
			if (is_iterable($result)) {
				yield from $result;
			}
			yield AwaitSignal::SIG_FINISH;
		};
		$g = $aa();
		$loop->add(static function($unsubscribe) use ($g, $aa) : void {
			for ($i = 0; $i < 5; $i++) {
				if(!$g->valid()){
					break;
				}
				$d = $g->current();
				switch ($d) {
					case AwaitSignal::SIG_WAIT:
						break;
					case AwaitSignal::SIG_FINISH:
					case AwaitSignal::SIG_INTERRUPT:
						$unsubscribe();
						break 2;
				}
				$g->next();
			}
		});
	}
}
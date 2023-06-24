<?php

namespace libasync\await;

use Closure;
use Generator;
use GlobalLogger;
use libasync\global\GlobalRuntime;
use libasync\runtime\AsyncRuntime;
use libasync\utils\Utils;
use pocketmine\utils\Utils as PMMPUtils;
use RuntimeException;

final class Await {
	private function __construct() { }

	/**
	 * @template T
	 * @param Closure():T $do
	 * @return T
	 * @throws \libasync\exception\AsyncExecutionException
	 */
	public static function async(Closure $do, ?AsyncRuntime $runtime = null, ?Closure $extraArgPrepareFunc = null, ?Closure $extraArgDestroyFunc = null, ?EventLoop $loop = null) {
		$loop ??= GlobalRuntime::getLoop();
		$runtime ??= GlobalRuntime::getRuntime();

		$callTrace = yield AwaitSignal::SIG_SET_TRACE;
		$rec = $runtime->runAsync($do, $extraArgPrepareFunc, $extraArgDestroyFunc, $callTrace);

		$loop->add(static function($unsubscribe) use ($rec) : void {
			if ($rec->isFinished()) {
				$unsubscribe();
			}
		});

		yield from $rec->awaitFinish();

		if ($rec->getError() !== null) {
			$rec->getError()->printWithCallTrace(GlobalLogger::get(), $callTrace);
			throw new RuntimeException(Utils::printPromiseExceptionMessage($rec->getError()));
		}

		return $rec->getResult();
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

	public static function tick(Closure $do, int $tick, int $times) : Generator {
		$c = true;
		$cancel = static function() use (&$c) { $c = false;};
		while ($times-- > 0 && $c) {
			yield from self::usleep($tick * (1000 / 20));
			$do($cancel);
		}
	}

	public static function interrupt() : Generator {
		while (true) {
			yield AwaitSignal::SIG_INTERRUPT;
		}
	}

	public static function do(Generator|callable $do, ?EventLoop $loop = null) {
		if ($do instanceof Generator) {
			$do = static fn() => yield from $do;
		}
		self::sync($do, $loop);
	}

	public static function sync(callable $do, ?EventLoop $loop = null) {
		$callTrace = PMMPUtils::printableCurrentTrace();
		if ($loop === null) {
			$loop = GlobalRuntime::getLoop();
		}
		$aa = static function() use ($do) {
			$result = $do();
			if (is_iterable($result)) {
				yield from $result;
			}
			yield AwaitSignal::SIG_FINISH;
		};
		$g = $aa();
		$loop->add(static function($unsubscribe) use ($callTrace, $g) : void {
			for ($i = 0; $i < 2; $i++) {
				if (!$g->valid()) {
					break;
				}
				$d = $g->current();
				switch ($d) {
					case AwaitSignal::SIG_SET_TRACE:
						$g->send($callTrace);
						break;
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
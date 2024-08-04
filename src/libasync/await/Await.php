<?php

namespace libasync\await;

use Closure;
use DaveRandom\CallbackValidator\CallbackType;
use DaveRandom\CallbackValidator\ReturnType;
use Generator;
use libasync\exception\ExecutionException;
use libasync\global\GlobalAsyncRuntime;
use libasync\runtime\AsyncExecutionEnvironment;
use libasync\runtime\AsyncRuntime;
use pocketmine\utils\Utils as PMMPUtils;
use Throwable;
use function libasync\may_drop;
use const bootstrap\PRODUCTION;

final class Await {
	private function __construct() { }

	public static function f2c(Closure $f) : Generator {
		$v = $f();
		if ($v instanceof Generator) {
			yield from $v;
		}
	}

	/** @return Generator<void,AwaitSignal,void,void> */
	public static function suspend() : Generator {
		yield AwaitSignal::SIG_WAIT;
	}

	public static function trap(Closure $trap) : Generator {
		yield AwaitSignal::SIG_TRAP;
		yield $trap;
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
		may_drop();
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


	public static function do(Closure|Generator $block, ?EventLoop $loop = null) : AwaitResult {
		try {
			if (!PRODUCTION && $block instanceof Closure) {
				PMMPUtils::validateCallableSignature(new CallbackType(new ReturnType(),), $block);
			}
			return new AwaitResult(
				static fn($errorHandler, $joined) => new Coroutine($block instanceof Closure ? self::f2c($block) : $block, $errorHandler, $joined),
				static fn(Coroutine $coroutine) => $coroutine->register($loop ?? GlobalAsyncRuntime::getLoop())
			);
		} catch (Throwable $thr) {
			throw $thr;
		}
	}
}

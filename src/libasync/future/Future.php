<?php

namespace libasync\future;

use Closure;
use libasync\exception\CancellationException;
use libasync\exception\ExecutionException;
use libasync\exception\TimeoutException;
use libasync\global\GlobalRuntime;
use libasync\runtime\AsyncExecutionEnvironment;
use libasync\runtime\AsyncExecutionReceipt;
use libasync\runtime\AsyncRuntime;
use pmmp\thread\ThreadSafe;
use pmmp\thread\ThreadSafeArray;
use pocketmine\utils\Utils;

/**
 * @template T of (scalar|null|ThreadSafe|ThreadSafeArray)
 */
class Future extends ThreadSafe implements FutureInterface {
	private bool $cancelled = false;
	private ThreadSafeArray $callTrace;

	/**
	 * @param AsyncExecutionReceipt<T> $receipt
	 */
	public function __construct(
		private readonly AsyncExecutionReceipt $receipt
	) {
		$this->callTrace = ThreadSafeArray::fromArray(Utils::printableCurrentTrace());
	}

	public static function async(Closure $do, ?AsyncRuntime $runtime = null, ?AsyncExecutionEnvironment $env = null) : Future {
		$runtime ??= GlobalRuntime::getRuntime();

		$rec = $runtime->runAsync($do, $env);
		return new Future($rec);
	}

	/**
	 * @return T
	 * @throws ExecutionException
	 * @throws TimeoutException
	 * @throws CancellationException
	 */
	public function get(int $timeout = PHP_INT_MAX) {
		if ($this->cancelled) {
			throw new CancellationException();
		}
		$targetTime = hrtime(true) + $timeout;
		while (((float) hrtime(true)) < $targetTime) {
			if ($this->receipt->isFinished()) {
				break;
			}
			usleep(5);
		}
		if (!$this->receipt->isFinished()) {
			throw new TimeoutException();
		}
		$error = $this->receipt->getError();
		if ($error !== null) {
			throw new ExecutionException($error, $this->callTrace);
		}
		return $this->receipt->getResult();
	}

	public function cancel() : void {
		$this->cancelled = true;
	}

	public function isCancelled() : bool {
		return $this->cancelled;
	}

	public function isDone() : bool {
		return $this->receipt->isFinished();
	}
}
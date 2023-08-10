<?php

namespace libasync\future;

use libasync\exception\CancellationException;
use libasync\exception\ExecutionException;
use libasync\exception\TimeoutException;
use pmmp\thread\ThreadSafe;
use pmmp\thread\ThreadSafeArray;

/**
 * @template T of (scalar|null|ThreadSafe|ThreadSafeArray)
 */
interface FutureInterface {
	/**
	 * @return T
	 * @throws ExecutionException
	 * @throws TimeoutException
	 * @throws CancellationException
	 */
	public function get(int $timeout = PHP_INT_MAX);

	public function cancel() : void;

	public function isCancelled() : bool;

	public function isDone() : bool;
}
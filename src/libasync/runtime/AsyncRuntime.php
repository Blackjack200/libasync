<?php

namespace libasync\runtime;

use Closure;
use pmmp\thread\ThreadSafe;
use pmmp\thread\ThreadSafeArray;

interface AsyncRuntime {
	/**
	 * @template T of (scalar|null|ThreadSafe|ThreadSafeArray)
	 * @param Closure(mixed ...$args):T $closure
	 * @return AsyncExecutionReceipt<T>
	 */
	public function runAsync(Closure $closure, ?AsyncExecutionEnvironment $env = null) : AsyncExecutionReceipt;
}
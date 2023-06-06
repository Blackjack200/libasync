<?php

namespace libasync\runtime;

use Closure;

interface AsyncRuntime {
	/**
	 * @param \Closure $closure
	 * @param null|Closure(AsyncExecutionRecipient):array $extraArgPrepareFunc
	 * @param null|Closure(...$args):void $extraArgDestroyFunc
	 */
	public function runAsync(Closure $closure, ?Closure $extraArgPrepareFunc = null, ?Closure $extraArgDestroyFunc = null) : AsyncExecutionRecipient;
}
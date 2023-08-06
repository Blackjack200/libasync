<?php

namespace libasync\runtime;

use Closure;
use libasync\utils\ClosureUtils;
use pocketmine\scheduler\AsyncPool;
use pocketmine\utils\Utils;
use const bootstrap\PRODUCTION;

class AsyncTaskRuntime implements AsyncRuntime {
	public function __construct(
		private readonly AsyncPool $pool
	) {
	}

	public function runAsync(Closure $closure, ?Closure $extraArgPrepareFunc = null, ?Closure $extraArgDestroyFunc = null, ?array $callTrace = null) : AsyncExecutionReceipt {
		if (!PRODUCTION) {
			ClosureUtils::validateThreadSafety($closure);
		}
		$rec = new AsyncExecutionReceipt();
		$rec->setCallTrace($callTrace ?? Utils::printableCurrentTrace());
		$task = new AsyncExecutionTask($rec, $closure, $extraArgPrepareFunc, $extraArgDestroyFunc);
		$this->pool->submitTask($task);
		return $rec;
	}
}
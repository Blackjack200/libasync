<?php

namespace libasync\runtime;

use Closure;
use libasync\utils\ClosureUtils;
use pocketmine\scheduler\AsyncPool;
use pocketmine\utils\Utils;

class AsyncTaskRuntime implements AsyncRuntime {
	public function __construct(
		private readonly AsyncPool $pool
	) {
	}

	public function runAsync(Closure $closure, ?AsyncExecutionEnvironment $env = null) : AsyncExecutionReceipt {
		ClosureUtils::validateThreadSafety($closure);
		$rec = new AsyncExecutionReceipt();
		$rec->setCallTrace(Utils::printableCurrentTrace());
		$task = new AsyncExecutionTask($rec, $closure, $env);
		$this->pool->submitTask($task);
		return $rec;
	}
}
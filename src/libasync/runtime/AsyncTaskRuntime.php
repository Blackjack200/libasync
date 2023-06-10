<?php

namespace libasync\runtime;

use Closure;
use pocketmine\scheduler\AsyncPool;
use pocketmine\utils\Utils;

class AsyncTaskRuntime implements AsyncRuntime {
	public function __construct(
		private readonly AsyncPool $pool
	) {
	}

	public function runAsync(Closure $closure, ?Closure $extraArgPrepareFunc = null, ?Closure $extraArgDestroyFunc = null, ?string $callTrace = null) : AsyncExecutionRecipient {
		$reci = new AsyncExecutionRecipient();
		$reci->setCallTrace($callTrace ?? \libasync\utils\Utils::smartSerialize(Utils::printableCurrentTrace()));
		$task = new AsyncExecutionTask($reci, $closure, $extraArgPrepareFunc, $extraArgDestroyFunc);
		$this->pool->submitTask($task);
		return $reci;
	}
}
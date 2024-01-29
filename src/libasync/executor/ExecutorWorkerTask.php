<?php

namespace libasync\executor;

use libasync\exception\ExecutionExceptionWrapper;
use libasync\runtime\AsyncExecutionEnvironment;
use libasync\runtime\AsyncExecutionReceipt;
use pmmp\thread\Runnable;
use pmmp\thread\Thread;
use pocketmine\utils\AssumptionFailedError;
use Throwable;

class ExecutorWorkerTask extends Runnable {
	public function __construct(
		private \Closure                   $closure,
		private AsyncExecutionReceipt      $receipt,
		private ?AsyncExecutionEnvironment $env = null
	) {

	}

	private function setError(AsyncExecutionReceipt $rec, Throwable $err) : void {
		$rec->setError(ExecutionExceptionWrapper::wrap($err));
	}

	public function run() : void {
		$thread = Thread::getCurrentThread();
		if (!($thread instanceof ExecutorWorker)) {
			throw new AssumptionFailedError("This should never happens.");
		}
		$params = ExecutorWorker::$paramsThreadLocal[spl_object_id($thread)];
		try {
			try {
				if ($this->env !== null) {
					$result = $this->env->run($this->closure, $params);
				} else {
					$result = ($this->closure)(...$params);
				}
				$this->receipt->setResult($result);
			} catch (Throwable $err) {
				$this->setError($this->receipt, $err);
			}
		} catch (Throwable $err) {
			$this->setError($this->receipt, $err);
		}
	}
}
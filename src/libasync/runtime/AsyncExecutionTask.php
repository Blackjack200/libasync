<?php

namespace libasync\runtime;

use Closure;
use libasync\exception\ExecutionExceptionWrapper;
use pocketmine\scheduler\AsyncTask;
use Throwable;

class AsyncExecutionTask extends AsyncTask {

	public function __construct(
		private readonly AsyncExecutionReceipt      $receipt,
		private readonly Closure                    $func,
		private readonly ?AsyncExecutionEnvironment $env,
	) {
	}

	public function onRun() : void {
		try {
			if ($this->env !== null) {
				$result = $this->env->run($this->func);
			} else {
				$result = ($this->func)();
			}
			$this->receipt->setResult($result);
		} catch (Throwable $err) {
			$this->receipt->setError(ExecutionExceptionWrapper::wrap($err));
		}
	}
}
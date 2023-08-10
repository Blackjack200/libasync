<?php

namespace libasync\runtime;

use Closure;
use libasync\exception\ExecutionExceptionWrapper;
use pocketmine\scheduler\AsyncTask;
use Throwable;

class AsyncExecutionTask extends AsyncTask {

	public function __construct(
		private readonly AsyncExecutionReceipt      $rec,
		private readonly Closure                    $closure,
		private readonly ?AsyncExecutionEnvironment $env,
	) {
	}

	public function onRun() : void {
		try {
			try {
				if ($this->env !== null) {
					$result = $this->env->run($this->closure);
				} else {
					$result = ($this->closure)();
				}
				$this->rec->setResult($result);
			} catch (Throwable $err) {
				$this->rec->setError(ExecutionExceptionWrapper::wrap($err));
			}
		} catch (Throwable $err) {
			$this->rec->setError(ExecutionExceptionWrapper::wrap($err));
		}
	}
}
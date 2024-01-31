<?php

namespace libasync\runtime;

use Closure;
use libasync\exception\ExecutionExceptionWrapper;
use pocketmine\scheduler\AsyncTask;
use Throwable;

class AsyncExecutionTask extends AsyncTask {
	private const THREAD_LOCAL_RECEIPT = 'receipt';
	private ?string $error = null;

	public function __construct(
		AsyncExecutionReceipt                       $receipt,
		private readonly Closure                    $func,
		private readonly ?AsyncExecutionEnvironment $env,
	) {
		$this->storeLocal(self::THREAD_LOCAL_RECEIPT, $receipt);
	}

	public function onRun() : void {
		try {
			if ($this->env !== null) {
				$result = $this->env->run($this->func);
			} else {
				$result = ($this->func)();
			}
			$this->setResult($result);
		} catch (Throwable $err) {
			$this->error = igbinary_serialize(ExecutionExceptionWrapper::wrap($err));
		}
	}

	public function onCompletion() : void {
		$receipt = $this->fetchLocal(self::THREAD_LOCAL_RECEIPT);
		assert($receipt instanceof AsyncExecutionReceipt);
		if ($this->error !== null) {
			$receipt->setError(igbinary_unserialize($this->error));
		} else {
			$receipt->setResult($this->getResult());
		}
	}
}
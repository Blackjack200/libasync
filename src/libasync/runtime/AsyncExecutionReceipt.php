<?php

namespace libasync\runtime;

use Generator;
use libasync\await\AwaitSignal;
use libasync\exception\ExecutionExceptionWrapper;
use pmmp\thread\ThreadSafe;
use pmmp\thread\ThreadSafeArray;

/**
 * @template T of (scalar|null|ThreadSafe|ThreadSafeArray)
 * T must be thread-safe or ig-binary serializable
 */
class AsyncExecutionReceipt {
	private mixed $result;
	private ?string $error = null;
	private bool $finished = false;
	/** @var string[] */
	protected array $callTrace;

	/**
	 * @return T
	 */
	public function getResult() {
		return $this->result;
	}

	public function setError(?ExecutionExceptionWrapper $error) : void {
		$this->error = igbinary_serialize($error);
		$this->setFinished();
	}

	public function getError() : ?ExecutionExceptionWrapper {
		if ($this->error === null) {
			return null;
		}
		return igbinary_unserialize($this->error);
	}

	public function isFinished() : bool {
		return $this->finished;
	}

	private function setFinished() : void {
		$this->finished = true;
	}

	public function setResult(mixed $result) : void {
		$this->result = $result;
		$this->setFinished();
	}

	public function setCallTrace(array $callTrace) : void {
		$this->callTrace = $callTrace;
	}

	public function getCallTrace() : array {
		return $this->callTrace;
	}

	public function yieldWait() : Generator {
		while (!$this->isFinished()) {
			yield AwaitSignal::SIG_WAIT;
		}
	}
}
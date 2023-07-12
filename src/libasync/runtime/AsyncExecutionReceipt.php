<?php

namespace libasync\runtime;

use Generator;
use libasync\await\Await;
use libasync\await\AwaitSignal;
use libasync\exception\AsyncExecutionException;
use libasync\utils\Utils;
use pmmp\thread\ThreadSafe;

/**
 * @template T
 * T must be thread-safe or igbinary serializable
 */
class AsyncExecutionReceipt extends ThreadSafe {
	private ThreadSafe|string $result;
	private ?string $error = null;
	private bool $finished = false;
	protected string $callTrace;

	/**
	 * @return T
	 */
	public function getResult() : mixed {
		return $this->synchronized(fn() => Utils::smartDeserialize($this->result));
	}

	public function setError(?AsyncExecutionException $error) : void {
		$this->synchronized(function(?AsyncExecutionException $error) {
			$this->error = igbinary_serialize($error);
			$this->setFinished();
		}, $error);
	}

	public function getError() : ?AsyncExecutionException {
		return $this->synchronized(function() {
			if ($this->error === null) {
				return null;
			}
			return igbinary_unserialize($this->error);
		});
	}

	public function isFinished() : bool {
		return $this->synchronized(fn() => $this->finished);
	}

	private function setFinished() : void {
		$this->synchronized(function() {
			$this->finished = true;
		});
	}

	public function setResult(mixed $result) : void {
		$this->synchronized(function(mixed $result) {
			$this->result = Utils::smartSerialize($result);
			$this->setFinished();
		}, $result);
	}

	public function setCallTrace(array $callTrace) : void {
		$this->synchronized(function(array $callTrace) {
			$this->callTrace = igbinary_serialize($callTrace);
		}, $callTrace);
	}

	public function getCallTrace() : array {
		return $this->synchronized(fn() => igbinary_unserialize($this->callTrace));
	}

	public function yieldWait() : Generator {
		while (!$this->isFinished()) {
			yield AwaitSignal::SIG_WAIT;
		}
	}

	public function suspendWait() : void {
		while (!$this->isFinished()) {
			Await::suspend(AwaitSignal::SIG_WAIT);
		}
	}
}
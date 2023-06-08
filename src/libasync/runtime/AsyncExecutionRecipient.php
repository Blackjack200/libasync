<?php

namespace libasync\runtime;

use Generator;
use libasync\await\AwaitSignal;
use libasync\exception\AsyncExecutionException;
use libasync\utils\Utils;
use pmmp\thread\ThreadSafe;

/**
 * @template T
 * T must be thread-safe or igbinary serializable
 */
class AsyncExecutionRecipient extends ThreadSafe {
	private ThreadSafe|string $result;
	private ?string $error = null;
	private bool $errored = false;
	private bool $finished = false;
	protected string $callTrace;

	/**
	 * @return T
	 */
	public function getResult() : mixed {
		return Utils::smartDeserialize($this->result);
	}

	public function getError() : ?AsyncExecutionException {
		if(!$this->errored){
			return null;
		}
		return igbinary_unserialize($this->error);
	}

	public function isFinished() : bool { return $this->finished; }

	private function setFinished() : void { $this->finished = true; }

	public function setResult(mixed $result) : void {
		$this->result = Utils::smartSerialize($result);
		$this->setFinished();
	}

	public function setError(?AsyncExecutionException $error) : void {
		$this->error = igbinary_serialize($error);
		$this->errored = true;
		$this->setFinished();
	}

	public function setCallTrace(string $callTrace) : void {
		$this->callTrace = $callTrace;
	}

	public function getCallTrace() : string {
		return $this->callTrace;
	}

	public function awaitFinish() : Generator {
		while (!$this->isFinished()) {
			yield AwaitSignal::SIG_WAIT;
		}
	}
}
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
	private ?AsyncExecutionException $error = null;
	private bool $finished = false;

	/**
	 * @return T
	 */
	public function getResult() : mixed {
		return Utils::smartDeserialize($this->result);
	}

	public function getError() : ?AsyncExecutionException { return $this->error; }

	public function isFinished() : bool { return $this->finished; }

	private function setFinished() : void { $this->finished = true; }

	public function setResult(mixed $result) : void {
		$this->result = Utils::smartSerialize($result);
		$this->setFinished();
	}

	public function setError(?AsyncExecutionException $error) : void {
		$this->error = $error;
		$this->setFinished();
	}

	public function awaitFinish() : Generator {
		while (!$this->isFinished()) {
			yield AwaitSignal::SIG_WAIT;
		}
	}
}
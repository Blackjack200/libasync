<?php

namespace libasync\await\lock\rw;

use Generator;
use libasync\await\AwaitSignal;
use libasync\await\lock\Lock;
use LogicException;

readonly class WriteLock implements Lock {
	public function __construct(
		/** @var BoxedValue<bool> */
		private BoxedValue $writing,
		/** @var BoxedValue<bool> */
		private BoxedValue $reading,
	) {
	}

	public function isLocked() : bool { return $this->writing->value; }

	public function lock() : Generator {
		while ($this->writing->value || $this->reading->value) {
			yield AwaitSignal::SIG_WAIT;
		}
		$this->writing->value = true;
	}

	public function unlock() : void {
		if (!$this->writing->value) {
			throw new LogicException("WriteLock hasn't been locked yet.");
		}
		$this->writing->value = false;
	}
}
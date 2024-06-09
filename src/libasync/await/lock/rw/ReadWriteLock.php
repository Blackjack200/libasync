<?php

namespace libasync\await\lock\rw;

use libasync\await\lock\Lock;

/**
 * @template T
 */
readonly class ReadWriteLock {
	protected ReadLock $reading;
	protected WriteLock $writing;

	public function __construct() {
		$read = new RefCell(false);
		$write = new RefCell(false);
		$this->reading = new ReadLock($write, $read);
		$this->writing = new WriteLock($write, $read);
	}

	public function read() : Lock {
		return $this->reading;
	}

	public function write() : Lock {
		return $this->writing;
	}
}
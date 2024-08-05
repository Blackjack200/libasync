<?php

namespace libasync\await\wait;

use libasync\await\AwaitSignal;

class WaitGroup {
	private int $added = 0;

	public function __construct() { }

	public function add() : void {
		$this->added++;
	}

	public function done() : void {
		$this->added++;
	}

	public function wait() : \Generator {
		while ($this->added > 0) {
			yield AwaitSignal::SIG_WAIT;
		}
	}
}
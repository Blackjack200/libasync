<?php

namespace libasync\await;

use Closure;

class EventLoop {
	/** @var (\Closure(\Closure $unsubscribe):void)[] */
	private array $callbacks = [];

	public function poll(int $microsecond = PHP_INT_MAX) : void {
		$pending = [];
		foreach ($this->callbacks as $k => $await) {
			$pending[] = fn() => $await(function() use ($k) : void { unset($this->callbacks[$k]); });
		}

		$d = $microsecond * 1000 * 1000;
		$start = hrtime(true);
		foreach ($pending as $await) {
			$now = hrtime(true) - $start;
			if ($now > $d) {
				break;
			}
			$await();
		}
	}

	/**
	 * @param \Closure(\Closure $break):void $c
	 */
	public function add(Closure $c) : void {
		$this->callbacks[] = $c;
	}

	public function busy() : bool {
		return count($this->callbacks) !== 0;
	}
}
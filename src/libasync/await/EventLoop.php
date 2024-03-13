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
	public function add(Closure $c) : Closure {
		$id = spl_object_id($c);
		$this->callbacks[$id] = $c;
		return function() use ($id) { unset($this->callbacks[$id]); };
	}

	public function busy() : bool {
		return count($this->callbacks) !== 0;
	}
}
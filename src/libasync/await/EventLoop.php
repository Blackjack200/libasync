<?php

namespace libasync\await;

use Closure;
use pmmp\thread\ThreadSafe;
use pmmp\thread\ThreadSafeArray;

class EventLoop extends ThreadSafe {
	/** @var (\Closure(\Closure $unsubscribe):void)[] */
	private ThreadSafeArray $callbacks;

	public function __construct() {
		$this->callbacks = new ThreadSafeArray();
	}

	public function poll(int $microsecond = PHP_INT_MAX) : void {
		$pending = [];
		$this->synchronized(function() use (&$pending) : void {
			foreach ($this->callbacks as $k => $await) {
				$pending[] = fn() => $await(function() use ($k) { $this->synchronized(function() use ($k) : void { unset($this->callbacks[$k]); }); });
			}
		});
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
	 * @param \Closure(\Closure $unsubscribe):void $c
	 */
	public function add(Closure $c) : void {
		$this->synchronized(function() use ($c) : void {
			$this->callbacks[] = $c;
		});
	}

	public function busy() : bool {
		return $this->synchronized(function() {
			return count($this->callbacks) !== 0;
		});
	}
}
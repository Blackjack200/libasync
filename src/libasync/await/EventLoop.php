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
		$this->synchronized(function() use ($microsecond) : void {
			$d = $microsecond * 1000 * 1000;
			$start = hrtime(true);
			foreach ($this->callbacks as $k => $await) {
				$now = hrtime(true) - $start;
				if ($now > $d) {
					break;
				}
				$await(function() use ($k) { $this->synchronized(function() use ($k) : void { unset($this->callbacks[$k]); }); });
			}
		});
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

	public function doOnce(Closure $c) : void {
		$this->synchronized(function() use ($c) : void {
			$this->callbacks[] = static function($un) use ($c) {
				$un();
				$c();
			};
		});
	}
}
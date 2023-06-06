<?php

namespace libasync\await;

use Closure;
use libasync\runtime\AsyncRuntime;
use pmmp\thread\ThreadSafe;
use pmmp\thread\ThreadSafeArray;

class EventLoop extends ThreadSafe {
	/** @var (\Closure(\Closure $unsubscribe):void)[] */
	private ThreadSafeArray $callbacks;

	public function __construct() {
		$this->callbacks = new ThreadSafeArray();
	}

	public function poll() : void {
		foreach ($this->callbacks as $k => $await) {
			$await(function() use ($k) { $this->synchronized(function() use ($k) : void { unset($this->callbacks[$k]); }); });
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
		return count($this->callbacks) !== 0;
	}
}
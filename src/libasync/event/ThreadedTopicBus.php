<?php

namespace libasync\event;

use Closure;
use pmmp\thread\ThreadSafe;
use pmmp\thread\ThreadSafeArray;

class ThreadedTopicBus extends ThreadSafe {
	private ThreadSafeArray $buffer;
	private ThreadSafeArray $handler;

	public function __construct() {
		$this->buffer = new ThreadSafeArray();
		$this->handler = new ThreadSafeArray();
	}

	public function process() : void {
		$pending = [];
		$this->buffer->synchronized(function() use (&$pending) : void {
			$this->handler->synchronized(function() use (&$pending) : void {
				while ($this->buffer->count() > 0) {
					$buf = $this->buffer->pop();
					[$topic, $val] = igbinary_unserialize($buf);
					foreach ($this->handler[$topic] ?? [] as $handler) {
						$pending[] = [$handler, $val];
					}
				}
			});
		});
		foreach ($pending as [$handler, $val]) {
			$handler(...$val);
		}
	}

	public function subscribe(string $topic, Closure $handler) : void {
		$this->handler->synchronized(function() use ($topic, $handler) : void {
			if (!isset($this->handler[$topic])) {
				$this->handler[$topic] = new ThreadSafeArray();
			}
			$this->handler[$topic][] = $handler;
		});
	}

	public function publish(string $topic, ...$value) : void {
		$this->buffer->synchronized(fn() => $this->buffer[] = igbinary_serialize([$topic, $value]));
	}
}
<?php

namespace libasync\event;

use Closure;
use pmmp\thread\ThreadSafe;
use pmmp\thread\ThreadSafeArray;

/**
 * @template T
 * @implements BusInterface<T>
 */
class ThreadedBus extends ThreadSafe implements BusInterface {
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
					$val = igbinary_unserialize($buf);
					foreach ($this->handler[get_debug_type($val)] ?? [] as $handler) {
						$pending[] = [$handler,$val];
					}
				}
			});
		});
		foreach ($pending as [$handler, $val]) {
			$handler($val);
		}
	}

	/**
	 * @param \Closure(T $topic):void $handler
	 */
	public function subscribe(Closure $handler) : void {
		$this->handler->synchronized(function() use ($handler) : void {
			foreach (ClosureParser::parse($handler) as $type) {
				$this->handler[$type][] = $handler;
			}
		});
	}

	public function publish(mixed $value) : void {
		$this->buffer->synchronized(fn() => $this->buffer[] = igbinary_serialize($value));
	}
}
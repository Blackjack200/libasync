<?php

namespace libasync\event;

use Closure;
use Volatile;

/**
 * @template T
 * @implements BusInterface<T>
 */
class ThreadedBus extends Volatile implements BusInterface {
	private Volatile $buffer;
	private Volatile $handler;

	public function __construct() {
		$this->buffer = new Volatile();
		$this->handler = new Volatile();
	}

	public function process() : void {
		$this->buffer->synchronized(function() : void {
			$this->handler->synchronized(function() : void {
				while ($this->buffer->count() > 0) {
					$buf = $this->buffer->pop();
					$val = igbinary_unserialize($buf);
					foreach ($this->handler[get_debug_type($val)] ?? [] as $handler) {
						$handler($val);
					}
				}
			});
		});
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
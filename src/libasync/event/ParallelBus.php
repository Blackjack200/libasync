<?php

namespace libasync\event;

use Closure;
use libasync\utils\ClosureUtils;
use libasync\utils\Utils;
use pmmp\thread\ThreadSafe;
use pmmp\thread\ThreadSafeArray;

/**
 * @template T
 * @implements BusInterface<T>
 */
class ParallelBus extends ThreadSafe implements BusInterface {
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
					$val = Utils::smartDeserialize($buf);
					foreach ($this->handler[get_debug_type($val)] ?? [] as $handler) {
						$pending[] = [$handler, $val];
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
		ClosureUtils::validateThreadSafety($handler);
		$this->handler->synchronized(function() use ($handler) : void {
			foreach (ClosureUtils::parseSubscriber($handler) as $type) {
				$this->handler[$type][] = $handler;
			}
		});
	}

	public function publish(mixed $value) : void {
		$this->buffer->synchronized(fn() => $this->buffer[] = Utils::smartSerialize($value));
	}

	public function hasSubscriber($value) : bool {
		return $this->synchronized(fn() => isset($this->handler[get_debug_type($value)]));
	}
}
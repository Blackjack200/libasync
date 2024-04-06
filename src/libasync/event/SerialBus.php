<?php

namespace libasync\event;

use Closure;
use libasync\utils\ClosureUtils;

/**
 * @template T
 */
final class SerialBus implements BusInterface {
	private array $handler;

	/**
	 * @param \Closure(T $topic):void $handler
	 */
	public function subscribe(Closure $handler) : void {
		foreach (ClosureUtils::parseSubscriber($handler) as $type) {
			$this->handler[$type][] = $handler;
		}
	}

	/**
	 * @param T $value
	 */
	public function publish(mixed $value) : void {
		foreach ($this->handler[get_debug_type($value)] ?? [] as $handler) {
			$handler($value);
		}
	}
}
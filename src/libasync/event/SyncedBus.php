<?php

namespace libasync\event;

use Closure;

/**
 * @template T
 */
final class SyncedBus implements BusInterface {
	private array $handler;

	/**
	 * @param \Closure(T $topic):void $handler
	 */
	public function subscribe(Closure $handler) : void {
		foreach (ClosureParser::parse($handler) as $type) {
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
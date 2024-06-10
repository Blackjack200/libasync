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
		$typ = get_debug_type($value);
		if (!isset($this->handler[$typ])) {
			return;
		}
		foreach ($this->handler[$typ] as $handler) {
			$handler($value);
		}
	}

	public function hasSubscriber($value) : bool {
		return isset($this->handler[get_debug_type($value)]);
	}
}
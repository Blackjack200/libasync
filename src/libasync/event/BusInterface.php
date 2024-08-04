<?php

namespace libasync\event;

/**
 * @template T
 */
interface BusInterface {
	/**
	 * @param \Closure(T $value):void $handler
	 */
	public function subscribe(\Closure $handler) : \Closure;

	/**
	 * @param T $value
	 */
	public function publish(mixed $value) : void;

	public function hasSubscriber($value) : bool;

	public function clear() : void;
}
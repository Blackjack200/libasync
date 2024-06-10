<?php

namespace libasync\event;

/**
 * @template T
 */
interface BusInterface {
	/**
	 * @param \Closure(T $value):void $handler
	 */
	public function subscribe(\Closure $handler) : void;

	/**
	 * @param T $value
	 */
	public function publish(mixed $value) : void;

	public function hasSubscriber($value) : bool;
}
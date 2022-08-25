<?php

namespace libasync\pool;


use Closure;
use libasync\utils\ResourceRef;

/**
 * @template T
 */
interface ResourcePoolInterface {
	/**
	 * @return ResourceRef<T>|null
	 */
	public function select(string $type) : ?ResourceRef;

	/**
	 * @param Closure():(T|null) $prepareFunc
	 * @param Closure(T):void $freeFunc
	 */
	public function register(string $type, Closure $prepareFunc, Closure $freeFunc) : void;

	public function close() : void;
}
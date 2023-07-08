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
	 * @param T $resource
	 */
	public function put(string $type, $resource) : void;

	/**
	 * @param Closure():(T|null) $prepareFunc
	 * @param Closure(T):void $freeFunc
	 * @param Closure(T,Closure(T):void $push):void $recycleFunc
	 */
	public function register(string $type, Closure $prepareFunc, Closure $freeFunc, Closure $recycleFunc) : void;

	public function close() : void;
}
<?php

namespace libasync;

use Closure;

/**
 * @template T
 */
interface PromiseInterface {
	/**
	 * @param Closure(T):void $cal
	 */
	public function whenFulfill(Closure $cal) : self;

	/**
	 * @param Closure(T):void $cal
	 */
	public function whenReject(Closure $cal) : self;

	/**
	 * @param Closure(PromiseException):void $cal
	 */
	public function catch(Closure $cal) : self;

	/**
	 * @param Closure $cal
	 */
	public function then(Closure $cal) : self;

	public function settle() : void;

	public function settleArgs(...$args) : void;

	/**
	 * @param string $class
	 */
	public function bind(string $class) : self;

	public function getErrorHandler() : ?Closure;

	public function getAsyncCall() : Closure;

	/**
	 * @phpstan-return (Closure(T):void)[]
	 * @return Closure[]
	 */
	public function getFulfillCallbacks() : array;

	/**
	 * @phpstan-return (Closure(T):void)[]
	 * @return Closure[]
	 */
	public function getRejectedCallbacks() : array;
}
<?php

namespace libasync;

use Closure;

interface PromiseInterface {
	public function whenFulfill(Closure $cal) : self;

	public function whenReject(Closure $cal) : self;

	public function catch(Closure $cal) : self;

	public function then(Closure $cal) : self;

	public function settle() : void;

	public function settleArgs(...$args) : void;

	public function bind(string $class) : self;

	public function getErrorHandler() : Closure;

	public function getAsyncCall() : Closure;

	/** @return Closure[] */
	public function getFulfillCallbacks() : array;

	/** @return Closure[] */
	public function getRejectedCallbacks() : array;
}
<?php

namespace libasync;

interface PromiseInterface {
	public function whenFulfill(callable $cal) : self;

	public function whenReject(callable $cal) : self;

	public function then(callable $cal) : self;

	public function start() : void;

	public function startWithArgs(...$args) : void;

	public function bind(string $class) : self;

	public function getAsyncCall() : callable;

	/** @return callable[] */
	public function getFulfillCallbacks() : array;

	/** @return callable[] */
	public function getRejectedCallbacks() : array;
}
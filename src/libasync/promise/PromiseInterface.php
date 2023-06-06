<?php

namespace libasync\promise;

use Closure;
use libasync\exception\AsyncExecutionException;

/**
 * @template ResultT
 * @template ReasonT
 */
interface PromiseInterface {
	/** @param Closure(ResultT):void $cal */
	public function whenFulfill(Closure $cal) : self;

	/** @param Closure(ReasonT):void $cal */
	public function whenReject(Closure $cal) : self;

	/** @param Closure(AsyncExecutionException):void $cal */
	public function catch(Closure $cal) : self;

	/** @param Closure(Closure(ResultT):void $resolve, Closure(ReasonT):void $reject, ...$param):void $cal */
	public function then(Closure $cal) : self;

	public function settle() : void;

	public function settleArgs(...$args) : void;

	/** @param class-string $class */
	public function bind(string $class) : self;

	/** @return Closure(AsyncExecutionException):void|null */
	public function getErrorHandler() : ?Closure;

	/** @return Closure(Closure(ResultT):void $resolve, Closure(ReasonT):void $reject, ...$param):void */
	public function getAsyncCall() : Closure;

	/** @return (Closure(ResultT):void)[] */
	public function getFulfillCallbacks() : array;

	/** @return (Closure(ReasonT):void)[] */
	public function getRejectedCallbacks() : array;
}
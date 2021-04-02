<?php


namespace libasync;


use Threaded;

interface Promise {
	/**
	 * @warn callable which execute synchronized
	 * @param callable() $cal
	 */
	public function with(callable $cal) : self;
	
	/**
	 * @warn callable which execute in main thread
	 * @param callable(mixed $ret) : bool $cal
	 */
	public function whenResult(callable $cal) : self;
	
	/**
	 * @return Threaded<callable() : bool>
	 */
	public function getAsync() : Threaded;
	
	/**
	 * @return callable[]
	 */
	public function getResultConsumer() : array;
}
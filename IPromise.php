<?php


namespace libasync;


use Threaded;

interface IPromise {
	/**
	 * @warn callable which execute synchronized
	 * @param callable() $cal
	 */
	public function then(callable $cal) : self;

	/**
	 * @warn callable which execute in main thread
	 * @param callable(mixed $ret) : bool $cal
	 */
	public function whenResult(callable $cal) : self;

	/**
	 * @return Threaded<callable>
	 */
	public function getAsyncConsumer() : Threaded;

	/**
	 * @return callable[]
	 */
	public function getResultConsumer() : array;
}
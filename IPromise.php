<?php


namespace libasync;


use Threaded;

interface IPromise {
	/**
	 * @thread main thread
	 * @param callable() $cal this callable will call at async-thread
	 */
	public function then(callable $cal) : self;

	/**
	 * @thread main thread
	 * @param callable(mixed $ret) : bool $cal this callable will call at main-thread
	 */
	public function whenResult(callable $cal) : self;

	/**
	 * @thread main thread
	 * @param callable(...$context) : bool $cal this callable will call at main-thread
	 */
	public function whenReject(callable $cal) : self;

	/**
	 * @thread main-thread
	 * @see IPromise::whenResult()
	 * This method call in whenResult to break context
	 */
	public function reject(...$reason) : void;

	/**
	 * @return Threaded<callable>
	 */
	public function getAsyncConsumer() : Threaded;

	/**
	 * @return callable[]
	 */
	public function getResultConsumer() : array;

	public function isRejected() : bool;

	public function getRejectConsumer() : callable;

	public function getRejectReason() : array;
}
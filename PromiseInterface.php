<?php

namespace libasync;

use Threaded;

interface PromiseInterface {
	/**
	 * @thread main thread
	 * @param callable $cal
	 * this callable will call at async-thread
	 */
	public function then(callable $cal) : self;

	/**
	 * @thread main thread
	 */
	public function whenResult(callable $cal) : self;

	/**
	 * @thread main thread
	 * @param callable(...$reason) : bool $cal
	 * this callable will call at main-thread
	 */
	public function whenReject(callable $cal) : self;

	/**
	 * @thread main-thread
	 * @see PromiseInterface::whenResult()
	 * This method call in whenResult to break context
	 */
	public function reject(...$reason) : void;

	public function start(...$args) : void;

	public function bind(string $class) : self;

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
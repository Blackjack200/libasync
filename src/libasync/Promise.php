<?php

namespace libasync;

use Closure;

class Promise implements PromiseInterface {
	protected Closure $async;
	protected ?Closure $onError = null;

	/** @var Closure[] */
	protected array $onFulfill = [];
	/** @var Closure[] */
	protected array $onReject = [];

	protected string $class = AsyncPromiseTask::class;

	public function __construct() {
		$empty = static fn() => null;
		$this->async = $empty;
	}

	public function bind(string $class) : self {
		$this->class = $class;
		return $this;
	}

	public function start() : void {
		$this->settle();
	}

	public function settle() : void {
		$this->settleArgs();
	}

	public function settleArgs(...$args) : void {
		$class = $this->class;
		$task = new $class($this, ...$args);
		$task->start();
	}

	public function whenFulfill(Closure $cal) : self {
		$this->onFulfill[] = $cal;
		return $this;
	}

	public function whenReject(Closure $cal) : self {
		$this->onReject[] = $cal;
		return $this;
	}

	public function then(Closure $cal) : self {
		$this->async = $cal;
		return $this;
	}

	public function getFulfillCallbacks() : array {
		return $this->onFulfill;
	}

	public function getRejectedCallbacks() : array {
		return $this->onReject;
	}

	public function getAsyncCall() : Closure {
		return $this->async;
	}

	public function catch(Closure $cal) : self {
		$this->onError = $cal;
		return $this;
	}

	public function getErrorHandler() : ?Closure {
		return $this->onError;
	}
}
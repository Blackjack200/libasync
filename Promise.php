<?php

namespace libasync;

class Promise implements PromiseInterface {
	/** @var callable */
	protected $async;
	/** @var callable[] */
	protected array $onFulfill = [];
	/** @var callable[] */
	protected array $onReject = [];
	protected string $class = PromiseAsyncTask::class;

	public function __construct() {
		$this->async = static function () : void { };
	}

	public function bind(string $class) : self {
		$this->class = $class;
		return $this;
	}

	public function start() : void {
		$this->startWithArgs();
	}

	public function startWithArgs(...$args) : void {
		$class = $this->class;
		$task = new $class($this, ...$args);
		$task->start();
	}

	public function whenFulfill(callable $cal) : self {
		$this->onFulfill[] = $cal;
		return $this;
	}

	public function whenReject(callable $cal) : self {
		$this->onReject[] = $cal;
		return $this;
	}

	public function then(callable $cal) : self {
		$this->async = $cal;
		return $this;
	}

	public function getFulfillCallbacks() : array {
		return $this->onFulfill;
	}

	public function getRejectedCallbacks() : array {
		return $this->onReject;
	}

	public function getAsyncCall() : callable {
		return $this->async;
	}
}
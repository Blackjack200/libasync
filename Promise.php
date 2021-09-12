<?php

namespace libasync;

use Closure;
use GlobalLogger;

class Promise implements PromiseInterface {
	protected Closure $async;
	/** @var Closure[] */
	protected array $onFulfill = [];
	/** @var Closure[] */
	protected array $onReject = [];
	protected Closure $onError;
	protected string $class = PromiseAsyncTask::class;


	public function __construct() {
		$empty = static function () : void { };
		$this->async = $empty;
		$this->onError = static function (PromiseException $err) : void {
			$err->print(GlobalLogger::get());
		};
	}

	public function bind(string $class) : self {
		$this->class = $class;
		return $this;
	}

	public function settle() : void {
		$this->settleArgs();
	}

	public function start() : void {
		$this->settle();
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

	public function getFulfillCallbacks() : array { return $this->onFulfill; }

	public function getRejectedCallbacks() : array { return $this->onReject; }

	public function getAsyncCall() : Closure { return $this->async; }

	public function catch(Closure $cal) : self {
		$this->onError = $cal;
		return $this;
	}

	public function getErrorHandler() : Closure {
		return $this->onError;
	}
}
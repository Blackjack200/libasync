<?php

namespace libasync;

use Closure;

/**
 * @template ResultT
 * @template ReasonT
 */
class Promise implements PromiseInterface {
	/** @var Closure(Closure(ResultT):void $resolve, Closure(ReasonT):void $reject, ...$param):void */
	protected Closure $async;
	/** @var Closure(PromiseException):void|null */
	protected ?Closure $onError = null;

	/** @var (Closure(ResultT):void)[] */
	protected array $onFulfill = [];
	/** @var (Closure(ReasonT):void)[] */
	protected array $onReject = [];
	/** @var class-string */
	protected string $class = AsyncPromiseTask::class;

	public function __construct() {
		$this->async = static fn() => null;
	}

	public function bind(string $class) : self {
		$this->class = $class;
		return $this;
	}

	public function start() : void { $this->settle(); }

	public function settle() : void { $this->settleArgs(); }

	public function settleArgs(...$args) : void {
		$class = $this->class;
		$task = new $class($this, ...$args);
		$task->start();
	}

	/** @param Closure(ResultT):void $cal */
	public function whenFulfill(Closure $cal) : self {
		$this->onFulfill[] = $cal;
		return $this;
	}

	/** @param Closure(ReasonT):void $cal */
	public function whenReject(Closure $cal) : self {
		$this->onReject[] = $cal;
		return $this;
	}

	/** @param Closure(Closure(ResultT):void $resolve, Closure(ReasonT):void $reject, ...$param):void $cal */
	public function then(Closure $cal) : self {
		$this->async = $cal;
		return $this;
	}

	/** @return (Closure(ResultT):void)[] */
	public function getFulfillCallbacks() : array { return $this->onFulfill; }

	/** @return (Closure(ReasonT):void)[] */
	public function getRejectedCallbacks() : array { return $this->onReject; }

	/** @return Closure(Closure(ResultT):void $resolve, Closure(ReasonT):void $reject, ...$param):void */
	public function getAsyncCall() : Closure { return $this->async; }

	/** @param Closure(PromiseException):void $cal */
	public function catch(Closure $cal) : self {
		$this->onError = $cal;
		return $this;
	}

	/** @return Closure(PromiseException):void|null */
	public function getErrorHandler() : ?Closure {
		return $this->onError;
	}

	/**
	 * @param PromiseInterface[] $promises
	 * @return Promise<void,void>
	 * 全部成功调用resolve,存在失败就调用reject
	 */
	public static function all(...$promises) : Promise {
		return (new Promise())
			->bind(PromiseAllTask::class)
			->then(static function(PromiseAllTask $t) use ($promises) : void {
				$t->promises = $promises;
			});
	}


	/**
	 * @param PromiseInterface[] $promises
	 * 第一个执行完成的Promise决定返回promise执行结果
	 */
	public static function race(...$promises) : Promise {
		return (new Promise())
			->bind(PromiseRaceTask::class)
			->then(static function(PromiseRaceTask $t) use ($promises) : void {
				$t->promises = $promises;
			});
	}
}
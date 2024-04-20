<?php

namespace libasync\await\lock\rw;

use Closure;
use libasync\await\Await;

/**
 * @template T
 */
class LockedValue {
	protected ReadWriteLock $lock;
	/** @var T|null */
	protected $lastWrite = null;

	public function __construct(
		/** @var T */
		private $value
	) {
		$this->lock = new ReadWriteLock();
	}

	/**
	 * @param Closure(Closure(T $v):void $set):mixed|\Generator $func
	 */
	public function set(Closure $func) {
		$w = $this->lock->write();
		yield from $w->lock();
		$r = yield from Await::f2c(fn() => $func(fn($v) => $this->value = $v));
		$w->unlock();
		return $r;
	}

	/**
	 * @param Closure(Closure(T $v):void $set):void|\Generator $func
	 */
	public function trySet(Closure $func, &$result) : bool|\Generator {
		$w = $this->lock->write();
		if ($w->isLocked()) {
			return false;
		}
		var_dump($w->isLocked());
		yield from $w->lock();
		var_dump($w->isLocked());
		$result = yield from Await::f2c(fn() => $func(function($v) {
			$this->value = $v;
			$this->lastWrite = $v;
		}));
		$w->unlock();
		return true;
	}

	/**
	 * @param Closure(T):mixed|\Generator $func
	 */
	public function get(Closure $func) {
		$r = $this->lock->write();
		yield from $r->lock();
		$r = yield from Await::f2c(fn() => $func($this->value));
		$r->unlock();
		return $r;
	}

	/** @return T|null */
	public function getLastWrite() { return $this->lastWrite; }
}
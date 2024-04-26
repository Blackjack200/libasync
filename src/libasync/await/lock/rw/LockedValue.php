<?php

namespace libasync\await\lock\rw;

use Closure;
use Generator;
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
	 * @param Closure(Closure(T $v):void $set,Closure():T $get):(void|Generator) $func
	 * @return Generator<void,void,void,bool>|bool
	 */
	public function set(Closure $func) {
		$w = $this->lock->write();
		yield from $w->lock();
		$r = yield from Await::f2c(fn() => $func(fn($v) => $this->value = $v, fn() => $this->value));
		$w->unlock();
		return $r;
	}

	/**
	 * @template TReturn of (Generator|mixed|null)
	 * @param Closure(Closure(T $v):void $set,Closure():T $get):TReturn $func
	 * @param TReturn &$result
	 * @return Generator<void,void,void,bool>|bool
	 */
	public function trySet(Closure $func, &$result) : bool|Generator {
		$w = $this->lock->write();
		if ($w->isLocked()) {
			return false;
		}
		yield from $w->lock();
		$result = yield from Await::f2c(fn() => $func(function($v) {
			$this->value = $v;
			$this->lastWrite = $v;
		}, fn() => $this->value));
		$w->unlock();
		return true;
	}

	/**
	 * @param Closure(T):(void|Generator) $func
	 * * @return Generator<void,void,void,T|null>|T|null
	 */
	public function get(Closure $func) {
		$l = $this->lock->write();
		yield from $l->lock();
		$ret = yield from Await::f2c(fn() => $func($this->value));
		$l->unlock();
		return $ret;
	}

	/** @return T|null */
	public function getLastWrite() { return $this->lastWrite; }
}
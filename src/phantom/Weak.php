<?php

namespace phantom;

use WeakReference;

/**
 * @template T of object
 */
final readonly class Weak {
	/** @var \WeakReference<T> */
	private \WeakReference $____________________ref;

	/** @param T $obj */
	public function __construct(object $obj) {
		$this->____________________ref = WeakReference::create($obj);
	}

	public static function __callStatic(string $name, array $arguments) {
		throw new \BadMethodCallException("you should not call this at all time.",);
	}

	public static function __set_state(array $an_array) : self {
		throw new \BadMethodCallException("you should not call this at all time.",);
	}

	/**
	 * @return T
	 */
	public function _ref() {
		return $this->____________________ref->get() ?? throw new LifetimeError("source object doesn't live long enough");
	}

	public function __call(string $name, array $arguments) {
		$obj = $this->____________________ref->get();
		if ($obj === null) {
			throw new LifetimeError("source object doesn't live long enough");
		}
		return (fn() => $this->$name(...$arguments))->call($obj);
	}

	public function __clone() : void {
		$obj = $this->____________________ref->get();
		if ($obj === null) {
			throw new LifetimeError("source object doesn't live long enough");
		}
	}

	public function __debugInfo() : ?array {
		$obj = $this->____________________ref->get();
		if ($obj === null) {
			throw new LifetimeError("source object doesn't live long enough");
		}
		return [$obj];
	}

	public function &__get(string $name) {
		$obj = $this->____________________ref->get();
		if ($obj === null) {
			throw new LifetimeError("source object doesn't live long enough");
		}
		$call = (function &() use ($name) {
			return $this->$name;
		})->call($obj);
		return $call;
	}

	public function __set(string $name, $value) : void {
		$obj = $this->____________________ref->get();
		if ($obj === null) {
			throw new LifetimeError("source object doesn't live long enough");
		}
		(fn() => $this->$name = $value)->call($obj);
	}

	public function __invoke(...$args) {
		$obj = $this->____________________ref->get();
		if ($obj === null) {
			throw new LifetimeError("source object doesn't live long enough");
		}
		return (fn() => $this(...$args))->call($obj);
	}

	public function __isset(string $name) : bool {
		$obj = $this->____________________ref->get();
		if ($obj === null) {
			throw new LifetimeError("source object doesn't live long enough");
		}
		return (fn() => isset($this->$name))->call($obj);
	}

	public function __serialize() : array {
		throw new \BadMethodCallException("you should not call this at all time.",);
	}

	public function __sleep() : array {
		throw new \BadMethodCallException("you should not call this at all time.",);
	}

	public function __toString() : string {
		$obj = $this->____________________ref->get();
		if ($obj === null) {
			throw new LifetimeError("source object doesn't live long enough");
		}
		return $obj->__toString();
	}

	public function __unserialize(array $data) : void {
		throw new \BadMethodCallException("you should not call this at all time.",);
	}

	public function __unset(string $name) : void {
		$obj = $this->____________________ref->get();
		if ($obj === null) {
			throw new LifetimeError("source object doesn't live long enough");
		}
		(function() : void { unset($this->$name); })->call($obj);
	}

	public function __wakeup() : void {
		throw new \BadMethodCallException("you should not call this at all time.",);
	}
}

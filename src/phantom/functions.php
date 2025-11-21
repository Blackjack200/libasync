<?php

/**
 *  > **Important:**
 *  > This library does *not* provide actual manual garbage collection.
 *  > It is a conceptual lifetime-checked wrapper and currently has **no functional
 *  > effect on object lifetimes**, due to Zend VM limitations
 *  > (see https://github.com/php/php-src/issues/17131).
 *
 *  A `Weak<T>` behaves like a proxy to the wrapped object.
 *  It allows:
 *  - Property access forwarding
 *  - Method call forwarding
 *  - Invocation forwarding
 *
 *  Whenever the underlying object has been garbage-collected,
 *  any access attempt will throw a {@see LifetimeError}.
 *
 *  This library is intended as a **lifetime-enforced façade**, not as a real GC tool.
 *  It can be used to:
 *  - Model lifetime semantics in userland
 *  - Signal unexpected early frees
 *  - Provide static-analysis hints for "lifetime-bound references"
 *  - Mimic experiments commonly found in systems languages
 *
 *  It **cannot** be used to:
 *  - Extend object lifetimes
 *  - Implement deterministic destruction
 *  - Detect reference cycles
 *  - Perform real manual GC
 */
namespace phantom;

/**
 * Create a weak reference wrapper for an object.
 * @template T of object
 * @param T $obj Object to be wrapped.
 * @return Weak<T> Weak reference wrapper.
 */
function weak(object $obj) : Weak {
	return new Weak($obj);
}

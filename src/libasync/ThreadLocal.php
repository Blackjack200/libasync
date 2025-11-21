<?php

namespace libasync;

use pmmp\thread\Thread;

/**
 * Thread-local storage for `ext-pmmpthread`.
 *
 * This class provides a simple thread-local context mechanism, allowing
 * you to store and retrieve values specific to the currently running thread.
 *
 * ⚠️ Note: This class only works in `ext-pmmpthread` Thread environments.
 * Other async runtimes or regular PHP threads will not have per-thread isolation.
 *
 * Usage:
 * ```
 * use libasync\ThreadLocal;
 *
 * // Register a value for the current thread
 * ThreadLocal::register(['foo' => 42]);
 *
 * // Fetch the value
 * $ctx = ThreadLocal::fetch();
 * echo $ctx['foo']; // 42
 *
 * // Fetch by reference
 * $ctxRef = &ThreadLocal::fetchRef();
 * $ctxRef['foo'] = 99;
 *
 * // Unregister when done
 * ThreadLocal::unregister();
 * ```
 *
 * @internal Designed specifically for PMMP threads; do not use in other async runtimes.
 */
final class ThreadLocal {
	/** @var array<int, mixed> Stores context per thread ID */
	private static array $contexts = [];

	/**
	 * Register a value for the current thread.
	 *
	 * @param mixed $value The value to store for this thread.
	 */
	public static function register($value) : void {
		self::$contexts[Thread::getCurrentThreadId()] = $value;
	}

	/**
	 * Unregister the value for the current thread.
	 */
	public static function unregister() : void {
		unset(self::$contexts[Thread::getCurrentThreadId()]);
	}

	/**
	 * Fetch the value stored for the current thread.
	 *
	 * @return mixed|null Returns the value, or null if nothing is registered.
	 */
	public static function fetch() {
		return self::$contexts[Thread::getCurrentThreadId()] ?? null;
	}

	/**
	 * Fetch the value stored for the current thread by reference.
	 *
	 * ⚠️ If no value is registered, this will emit a notice and return null.
	 *
	 * @return mixed Reference to the stored value.
	 */
	public static function &fetchRef() {
		return self::$contexts[Thread::getCurrentThreadId()];
	}
}
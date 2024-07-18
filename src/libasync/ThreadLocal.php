<?php

namespace libasync;

use pmmp\thread\Thread;

final class ThreadLocal {
	private static array $contexts = [];

	public static function register($value) : void {
		self::$contexts[Thread::getCurrentThreadId()] = $value;
	}

	public static function unregister() : void {
		unset(self::$contexts[Thread::getCurrentThreadId()]);
	}

	public static function fetch() {
		return self::$contexts[Thread::getCurrentThreadId()] ?? null;
	}

	public static function fetchRef() {
		return self::$contexts[Thread::getCurrentThreadId()];
	}
}
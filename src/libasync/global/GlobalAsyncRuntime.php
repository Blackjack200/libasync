<?php

namespace libasync\global;

use libasync\await\EventLoop;
use libasync\runtime\AsyncRuntime;

final class GlobalAsyncRuntime {
	private function __construct() { }
	private static ?AsyncRuntime $runtime = null;
	private static ?EventLoop $loop = null;

	public static function gerThreadedRuntime() : AsyncRuntime {
		if (self::$runtime === null) {
			throw new \RuntimeException('no default async runtime');
		}
		return self::$runtime;
	}

	public static function setRuntime(?AsyncRuntime $runtime) : void {
		self::$runtime = $runtime;
	}

	public static function getLoop() : EventLoop {
		if (self::$loop === null) {
			throw new \RuntimeException('no default event loop');
		}
		return self::$loop;
	}

	public static function setLoop(?EventLoop $loop) : void {
		self::$loop = $loop;
	}
}
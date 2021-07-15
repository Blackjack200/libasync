<?php

namespace libasync;

class Promises {
	private function __construct() {
	}

	public static function new() : Promise {
		return new Promise();
	}

	/**
	 * @param class-string<PromiseAsyncTask> $class
	 */
	public static function start(PromiseInterface $promise, string $class = PromiseAsyncTask::class, ...$args) : void {
		$task = new $class($promise, ...$args);
		$task->start();
	}
}
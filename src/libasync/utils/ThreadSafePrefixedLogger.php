<?php

namespace libasync\utils;

use pocketmine\thread\log\ThreadSafeLogger;

class ThreadSafePrefixedLogger extends ThreadSafeSimpleLogger {

	private ThreadSafeLogger $delegate;
	private string $prefix;

	public function __construct(ThreadSafeLogger $delegate, string $prefix) {
		$this->delegate = $delegate;
		$this->prefix = $prefix;
	}

	public function log($level, $message) {
		$this->delegate->log($level, "[$this->prefix] $message");
	}

	public function logException(\Throwable $e, $trace = null) {
		$this->delegate->logException($e, $trace);
	}

	/**
	 * @return string
	 */
	public function getPrefix() : string {
		return $this->prefix;
	}

	/**
	 * @param string $prefix
	 */
	public function setPrefix(string $prefix) : void {
		$this->prefix = $prefix;
	}
}

<?php

namespace libasync\utils;

use pmmp\thread\ThreadSafeArray;
use pocketmine\thread\log\ThreadSafeLogger;
use pocketmine\thread\log\ThreadSafeLoggerAttachment;

class ThreadSafePluginLogger extends ThreadSafePrefixedLogger implements \AttachableLogger {

	/**
	 * @var \Closure[]
	 * @phpstan-var ThreadSafeLoggerAttachment[]
	 */
	private ThreadSafeArray $attachments;

	public function __construct(ThreadSafeLogger $delegate, string $prefix) {
		parent::__construct($delegate, $prefix);
		$this->attachments = new ThreadSafeArray();
	}

	/**
	 * @phpstan-param ThreadSafeLoggerAttachment $attachment
	 */
	public function addAttachment(\Closure $attachment) {
		$this->attachments[spl_object_id($attachment)] = $attachment;
	}

	/**
	 * @phpstan-param ThreadSafeLoggerAttachment $attachment
	 */
	public function removeAttachment(\Closure $attachment) {
		unset($this->attachments[spl_object_id($attachment)]);
	}

	public function removeAttachments() {
		$this->attachments = new ThreadSafeArray();
	}

	public function getAttachments() {
		return $this->attachments;
	}

	public function log($level, $message) {
		parent::log($level, $message);
		foreach ($this->attachments as $attachment) {
			$attachment($level, $message);
		}
	}
}
<?php

namespace libasync;

use pocketmine\timings\Timings;
use pocketmine\timings\TimingsHandler;

/**
 * AsyncTimings provides a centralized timing handler system for asynchronous
 * operations in PocketMine-MP, including coroutine resumes.
 *
 * This class wraps `TimingsHandler` to allow dynamic creation and reuse of
 * timing handlers for asynchronous tasks, while maintaining a single root
 * async handler attached to the server's scheduler.
 *
 * > ⚠️ Only intended for PMMP integrations. Do not use outside of PMMP.
 *
 * @example Basic usage:
 *   use libasync\AsyncTimings;
 *
 *   // Get or create a timing handler for an async task
 *   $handler = AsyncTimings::getByName("MyAsyncTask");
 *   $handler->startTiming();
 *   // ... do async operation ...
 *   $handler->stopTiming();
 *
 *   // Get or create a timing handler specifically for coroutine resume
 *   $resumeHandler = AsyncTimings::getResumeByName("MyCoroutine");
 *   $resumeHandler->startTiming();
 *   // ... resume coroutine ...
 *   $resumeHandler->stopTiming();
 *
 * @internal Integrates with PocketMine-MP Timings system.
 */
final class AsyncTimings {
	/** @var bool Whether AsyncTimings has been initialized */
	private static bool $init = false;

	/** @var TimingsHandler Root handler for all async timing handlers */
	public static TimingsHandler $asyncHandler;

	/** @var TimingsHandler[] Map of async handler names to TimingsHandler */
	public static array $timingMap = [];

	/** @var TimingsHandler[] Map of coroutine resume names to TimingsHandler */
	public static array $coroutineResume = [];

	/** Private constructor to prevent instantiation */
	private function __construct() { }

	/**
	 * Initialize the async timing system.
	 * Creates the root async handler attached to the PMMP scheduler.
	 */
	public static function init() : void {
		if (self::$init) {
			return;
		}
		self::$init = true;
		self::$asyncHandler = new TimingsHandler("Async Handlers", Timings::$scheduler);
	}

	/**
	 * Get or create a TimingsHandler by name for async operations.
	 *
	 * @param string $name Name of the handler.
	 * @return TimingsHandler
	 */
	public static function getByName(string $name) : TimingsHandler {
		self::init();
		if (!isset(self::$timingMap[$name])) {
			self::$timingMap[$name] = new TimingsHandler($name, self::$asyncHandler);
		}
		return self::$timingMap[$name];
	}

	/**
	 * Get or create a TimingsHandler for coroutine resume by name.
	 *
	 * @param string $name Name of the coroutine resume handler.
	 * @return TimingsHandler
	 */
	public static function getResumeByName(string $name) : TimingsHandler {
		self::init();
		if (!isset(self::$coroutineResume[$name])) {
			self::$coroutineResume[$name] = new TimingsHandler("Resume: " . $name, self::$asyncHandler);
		}
		return self::$coroutineResume[$name];
	}
}
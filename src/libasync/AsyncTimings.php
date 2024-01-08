<?php

namespace libasync;

use pocketmine\timings\Timings;
use pocketmine\timings\TimingsHandler;

final class AsyncTimings {
	private static bool $init = false;
	public static TimingsHandler $asyncHandler;
	/** @var TimingsHandler[] */
	public static array $timingMap = [];
	/** @var TimingsHandler[] */
	public static array $coroutineResume = [];

	private function __construct() { }

	public static function init() : void {
		if (self::$init) {
			return;
		}
		self::$init = true;
		self::$asyncHandler = new TimingsHandler("Async Handlers", Timings::$scheduler);
	}

	public static function getByName(string $name) : TimingsHandler {
		self::init();
		if (!isset(self::$timingMap[$name])) {
			self::$timingMap[$name] = new TimingsHandler($name, self::$asyncHandler);
		}
		return self::$timingMap[$name];
	}

	public static function getResumeByName(string $name) : TimingsHandler {
		self::init();
		if (!isset(self::$coroutineResume[$name])) {
			self::$coroutineResume[$name] = new TimingsHandler("Resume: " . $name, self::$asyncHandler);
		}
		return self::$coroutineResume[$name];
	}
}
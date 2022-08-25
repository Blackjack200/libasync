<?php

namespace libasync\utils;

use InvalidArgumentException;
use libasync\promise\PromiseException;
use pocketmine\errorhandler\ErrorTypeToStringMap;
use pocketmine\utils\Filesystem;

final class Utils {
	private function __construct() { }

	public static function printPromiseExceptionMessage(PromiseException $e) : string {
		$errstr = preg_replace('/\s+/', ' ', trim($e->getMessage()));

		$errno = $e->getCode();
		if (is_int($errno)) {
			try {
				$errno = ErrorTypeToStringMap::get($errno);
			} catch (InvalidArgumentException) {
				//pass
			}
		}

		$errfile = Filesystem::cleanPath($e->getFile());
		$errline = $e->getLine();

		return $e->getClass() . ": \"$errstr\" ($errno) in \"$errfile\" at line $errline";
	}
}
<?php

namespace libasync\utils;

use InvalidArgumentException;
use libasync\exception\AsyncExecutionException;
use pmmp\thread\ThreadSafe;
use pmmp\thread\ThreadSafeArray;
use pocketmine\errorhandler\ErrorTypeToStringMap;
use pocketmine\utils\Filesystem;

final class Utils {
	private function __construct() {
		$serializeArr = static function(array $arr) : ThreadSafeArray {
			$new = new ThreadSafeArray();
			foreach ($arr as $elem) {
				$new[] = Utils::smartSerialize($elem);
			}
			return $new;
		};
		$deserializeArr = static function(ThreadSafeArray $arr) : array {
			$new = [];
			foreach ($arr as $elem) {
				$new[] = Utils::smartDeserialize($elem);
			}
			return $new;
		};
	}

	public static function printPromiseExceptionMessage(AsyncExecutionException $e) : string {
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

	public static function smartSerialize(mixed $m) : ThreadSafe|null|string {
		if ($m instanceof ThreadSafe) {
			return $m;
		}
		return igbinary_serialize($m);
	}

	public static function smartDeserialize(mixed $m) : mixed {
		if ($m instanceof ThreadSafe) {
			return $m;
		}
		return igbinary_unserialize($m);
	}
}
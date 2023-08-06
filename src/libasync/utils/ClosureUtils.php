<?php

namespace libasync\utils;

use pmmp\thread\ThreadSafe;
use pmmp\thread\ThreadSafeArray;

final class ClosureUtils {
	private function __construct() { }

	/**
	 * @throws \RuntimeException
	 */
	public static function validateThreadSafety(\Closure $c, int $offset = 0) : void {
		try {
			$ref = new \ReflectionFunction($c);
			if (!$ref->isStatic()) {
				throw new \RuntimeException('thread safe closure must be static');
			}
		} catch (\ReflectionException $exception) {
			throw new \RuntimeException('reflection failed', 0, $exception);
		}
	}

	private static function validateType(\ReflectionType|\ReflectionNamedType|\ReflectionIntersectionType|\ReflectionUnionType $typ) : void {
		$types = [];
		if ($typ instanceof \ReflectionNamedType) {
			$types = [$typ];
		}
		if ($typ instanceof \ReflectionUnionType) {
			$types = $typ->getTypes();
		}
		if ($typ instanceof \ReflectionIntersectionType) {
			$types = $typ->getTypes();
		}
		foreach ($types as $type) {
			if (!$type->isBuiltin() && !in_array($type->getName(), [
					ThreadSafe::class,
					ThreadSafeArray::class,
					\Closure::class,
				], true)) {
				throw new \RuntimeException("invalid param {$type->getName()}");
			}
		}
	}
}
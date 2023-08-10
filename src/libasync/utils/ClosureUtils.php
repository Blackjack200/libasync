<?php

namespace libasync\utils;

use Closure;
use InvalidArgumentException;
use pmmp\thread\ThreadSafe;
use pmmp\thread\ThreadSafeArray;
use ReflectionFunction;
use ReflectionNamedType;
use ReflectionUnionType;
use const bootstrap\PRODUCTION;

final class ClosureUtils {
	private function __construct() { }

	public static function validateParamCount(\Closure $c, int $expected) : void {
		if (PRODUCTION) {
			return;
		}
		$ref = new \ReflectionFunction($c);
		$h = count($ref->getParameters());
		if ($h !== $expected) {
			throw new \RuntimeException("param count mismatched: $h vs $expected");
		}
	}

	public static function validateStatic(\Closure $c) : void {
		if (PRODUCTION) {
			return;
		}
		$ref = new \ReflectionFunction($c);
		if (!$ref->isStatic()) {
			throw new \RuntimeException('static closure required');
		}
	}

	/**
	 * @throws \RuntimeException
	 */
	public static function validateThreadSafety(\Closure $c, int $offset = 0) : void {
		if (PRODUCTION) {
			return;
		}
		try {
			$ref = new \ReflectionFunction($c);
			if (!$ref->isStatic()) {
				throw new \RuntimeException('thread safe closure must be static');
			}
			$ret = $ref->getReturnType();
			if ($ret !== null) {
				self::validateType($ret);
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

	/**
	 * @return string[]
	 */
	public static function parseSubscriber(Closure $closure) : array {
		$params = (new ReflectionFunction($closure))->getParameters();
		$paramCnt = count($params);
		if ($paramCnt !== 1) {
			throw new InvalidArgumentException("invalid closure with $paramCnt param");
		}
		[$param] = $params;
		$type = $param->getType();
		if ($type === null) {
			throw new InvalidArgumentException("invalid param with no type specified");
		}
		$types = match (true) {
			$type instanceof ReflectionNamedType => [$type],
			$type instanceof ReflectionUnionType => $type->getTypes()
		};
		$arr = [];
		foreach ($types as $t) {
			$arr[] = $t->getName();
		}
		return $arr;
	}
}
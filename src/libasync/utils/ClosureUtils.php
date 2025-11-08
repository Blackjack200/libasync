<?php

namespace libasync\utils;

use Closure;
use InvalidArgumentException;
use Opis\Closure\ReflectionClosure;
use Opis\Closure\Serializer;
use pmmp\thread\ThreadSafe;
use pmmp\thread\ThreadSafeArray;
use ReflectionFunction;
use ReflectionNamedType;
use ReflectionUnionType;
use const bootstrap\PRODUCTION;

Serializer::init();
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

	public static function noCyclic(Closure $closure, object $root) : void {
		$ref = new ReflectionClosure($closure);

		if ($ref->getClosureThis() === $root) {
			throw new InvalidArgumentException("closure directly scoped to root");
		}

		foreach ($ref->getUseVariables() as $name => $value) {
			if (self::containsObject($value, $root)) {
				throw new InvalidArgumentException("closure used cyclic reference on $name");
			}
		}
	}

	private static function containsObject(mixed $value, object $root, array &$seen = []) : bool {
		if (is_object($value)) {
			$oid = spl_object_id($value);
			if (isset($seen[$oid])) {
				return false;
			}
			$seen[$oid] = true;

			if ($value === $root) {
				return true;
			}

			if (array_any((array) $value, static fn($prop) => self::containsObject($prop, $root, $seen))) {
				return true;
			}
		} else if (is_array($value)) {
			if (array_any($value, static fn($v) => self::containsObject($v, $root, $seen))) {
				return true;
			}
		}
		return false;
	}
}
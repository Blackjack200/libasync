<?php

namespace libasync\event;

use Closure;
use InvalidArgumentException;
use ReflectionFunction;
use ReflectionNamedType;
use ReflectionUnionType;

final class ClosureParser {
	private function __construct() { }

	/**
	 * @return string[]
	 */
	public static function parse(Closure $closure) : array {
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
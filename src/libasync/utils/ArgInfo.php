<?php


namespace libasync\utils;

use Closure;

/** @template V */
class ArgInfo {
	/** @var V */
	public mixed $value;
	/** @var callable */
	public $func;

	/**
	 * @param V $value
	 */
	public function __construct(mixed $value, callable $func) {
		$this->value = $value;
		$this->func = $func;
	}

	public static function new(mixed $value, Closure $func) : self {
		return new self($value, $func);
	}
}
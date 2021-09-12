<?php


namespace libasync;

/** @template V */
class ArgInfo {
	/** @var V */
	public mixed $value;
	/** @var callable */
	public $finalizeFunction;

	/**
	 * @param V $value
	 */
	public function __construct(mixed $value, callable $finalizeFunction) {
		$this->value = $value;
		$this->finalizeFunction = $finalizeFunction;
	}
}
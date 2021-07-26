<?php


namespace libasync;


class ArgInfo {
	/** @var mixed|null */
	public $value;
	/** @var callable */
	public $finalizeFunction;

	public function __construct($value, callable $finalizeFunction) {
		$this->value = $value;
		$this->finalizeFunction = $finalizeFunction;
	}
}
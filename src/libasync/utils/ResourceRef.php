<?php

namespace libasync\utils;

use Closure;

/**
 * @template T
 */
class ResourceRef {
	/**
	 * @param T $val
	 * @param Closure(T):void $freeFunc
	 */
	public function __construct(
		private mixed   $val,
		private Closure $freeFunc
	) {
	}

	public function get() : mixed { return $this->val; }

	public function free() : void {
		($this->freeFunc)($this->val, false);
	}
}
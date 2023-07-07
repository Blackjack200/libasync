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
		private readonly mixed   $val,
		private readonly Closure $freeFunc,
		private readonly Closure $recycleFunc
	) {
	}

	public function get() : mixed { return $this->val; }

	public function free() : void {
		($this->freeFunc)($this->val, false);
	}

	public function recycle() : bool {
		return ($this->recycleFunc)($this->val);
	}
}
<?php

namespace libasync\utils;

use Closure;

/**
 * @template T
 */
class ResourceRef {
	private ?Closure $onClose = null;

	/**
	 * @param T $res
	 * @param Closure(T):bool $freeFunc
	 */
	public function __construct(
		private mixed   $res,
		private bool    $recyclable,
		private Closure $freeFunc,
		private Closure $recycleFunc
	) {
	}

	/**
	 * @return T
	 */
	public function get() : mixed { return $this->res; }

	public function isRecyclable() : bool { return $this->recyclable; }

	public function close(bool $force = false) : void {
		if ($this->recyclable) {
			$recycled = ($this->recycleFunc)($this->res);
			if (!$recycled) {
				goto free;
			}
		} else {
			free:
			($this->freeFunc)($this->res, $force);
		}
		if ($this->onClose !== null) {
			($this->onClose)($this->res);
		}
	}

	public function onClose(?Closure $onClose) : void { $this->onClose = $onClose; }
}
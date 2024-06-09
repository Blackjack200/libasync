<?php

namespace libasync\await\lock\rw;

/**
 * @internal
 * @template T
 */
class RefCell {
	public function __construct(
		/** @var T */
		public $value
	) {
	}
}
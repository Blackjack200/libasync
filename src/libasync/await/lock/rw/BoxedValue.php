<?php

namespace libasync\await\lock\rw;

/**
 * @internal
 * @template T
 */
class BoxedValue {
	public function __construct(
		/** @var T */
		public $value
	) {
	}
}
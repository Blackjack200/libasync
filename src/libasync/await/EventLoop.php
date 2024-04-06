<?php

namespace libasync\await;

use Closure;

interface EventLoop {
	/**
	 * @param Closure(Closure $break):void $c
	 * @return Closure() remove from loop
	 */
	public function add(Closure $c) : Closure;
}
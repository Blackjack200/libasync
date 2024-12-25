<?php

namespace libasync\await;

use Closure;

interface EventLoop {
	/**
	 * @param Closure(Closure $break,Closure $changeToWakeupMode):void $c
	 * @return Closure called when $c is finished, it removes $c from loop
	 */
	public function add(Closure $c) : Closure;
}
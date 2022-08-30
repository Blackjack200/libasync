<?php

namespace libasync\channel;

class SubscriberHandler {
	public function __construct(private \Closure $removeFunc) { }

	public function remove() : void {
		($this->removeFunc)();
	}
}
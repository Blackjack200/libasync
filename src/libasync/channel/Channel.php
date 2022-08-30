<?php

namespace libasync\channel;

use Closure;

class Channel {
	/** @var \Closure[] */
	private array $subscribers = [];

	public function __construct() { }

	public function send(...$param) : void {
		foreach ($this->subscribers as $subscriber) {
			$subscriber(...$param);
		}
	}

	public function subscribe(Closure $c) : SubscriberHandler {
		$this->subscribers[spl_object_hash($c)] = $c;
		return new SubscriberHandler(function() use ($c) : void { unset($this->subscribers[spl_object_hash($c)]); });
	}
}
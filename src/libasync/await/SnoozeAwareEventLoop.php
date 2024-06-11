<?php

namespace libasync\await;

use Closure;
use pocketmine\snooze\SleeperHandler;
use pocketmine\snooze\SleeperNotifier;

class SnoozeAwareEventLoop implements EventLoop {
	/** @var \SplObjectStorage<Closure(Closure $unsubscribe):void> */
	private \SplObjectStorage $callbacks;
	private readonly SleeperNotifier $notifier;

	public function __construct(
		SleeperHandler $handler
	) {
		$this->callbacks = new \SplObjectStorage();
		$this->notifier = $handler->addNotifier(fn() => $this->poll(5))->createNotifier();
	}

	public function poll(int $microsecond = PHP_INT_MAX) : void {
		$pending = [];
		foreach ($this->callbacks as $await) {
			$pending[] = fn() => $await(fn() => $this->callbacks->detach($await));
		}

		$d = $microsecond * 1000 * 1000;
		$start = hrtime(true);
		foreach ($pending as $await) {
			$now = hrtime(true) - $start;
			if ($now >= $d) {
				break;
			}
			$await();
		}
	}

	public function busy() : bool {
		return count($this->callbacks) !== 0;
	}

	/**
	 * @param Closure(Closure $break):void $c
	 */
	public function add(Closure $c) : Closure {
		$this->callbacks->attach($c);
		return fn() => $this->callbacks->detach($c);
	}

	public function wakeupSleeper() : void {
		$this->notifier->wakeupSleeper();
	}
}
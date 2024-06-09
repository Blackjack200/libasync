<?php

namespace libasync\await;

use Closure;
use pocketmine\snooze\SleeperHandler;
use pocketmine\snooze\SleeperNotifier;

class SnoozeAwareEventLoop implements EventLoop {
	/** @var \SplObjectStorage<Closure(Closure $unsubscribe):void> */
	private \SplObjectStorage $callbacks;
	private int $notifierCounter = 0;
	private readonly SleeperNotifier $notifier;

	public function __construct(
		SleeperHandler $handler
	) {
		$this->callbacks = new \SplObjectStorage();
		$this->notifier = $handler->addNotifier(fn() => $this->poll(5))->createNotifier();
	}

	public function poll(int $microsecond = PHP_INT_MAX) : void {
		//var_dump($this->notifierCounter . "pending...");
		$pending = [];
		foreach ($this->callbacks as $await) {
			$pending[] = fn() => $await(function() use ($await) : void {
				$this->callbacks->detach($await);
				$this->notifierCounter--;
			});
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
		foreach ($this->callbacks as $callback) {
			//	var_dump(Utils::getNiceClosureName($callback));
		}
		//var_dump($this->notifierCounter . "left...");
	}

	public function busy() : bool {
		return $this->notifierCounter !== 0;
	}

	/**
	 * @param Closure(Closure $break):void $c
	 */
	public function add(Closure $c) : Closure {
		$this->callbacks->attach($c);
		$this->notifierCounter++;
		return function() use ($c) {
			$this->callbacks->detach($c);
			$this->notifierCounter--;
			$this->notifier->wakeupSleeper();
		};
	}

	public function wakeupSleeper() : void {
		$this->notifier->wakeupSleeper();
	}
}
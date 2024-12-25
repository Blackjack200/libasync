<?php
declare(strict_types=1);

namespace libasync\await;

use Closure;
use SplQueue;

class ClassicEventLoop implements EventLoop {
	/** @var array<int, EventLoopTask> */
	private array $polling = [];
	/** @var array<int, EventLoopTask> */
	private array $waiting = [];
	/** @var SplQueue<int> */
	private SplQueue $wakeupQueue;

	public function __construct() {
		$this->wakeupQueue = new SplQueue();
	}

	public function poll(int $microsecond = PHP_INT_MAX) : void {
		$deadline = hrtime(true) + ($microsecond * 1000000);

		foreach ($this->polling as $task) {
			if (hrtime(true) >= $deadline) {
				break;
			}
			$task->execute();
		}

		while (!$this->wakeupQueue->isEmpty()) {
			$id = $this->wakeupQueue->dequeue();
			$this->polling[$id] = $this->waiting[$id];
			unset($this->waiting[$id]);
		}
	}

	public function add(Closure $c, int $timeoutMicrosecond = PHP_INT_MAX, ?Closure $onTimeout = null) : EventLoopTask {
		$id = spl_object_id($c);
		$task = new EventLoopTask(
			$c,
			function() use ($id) {
				unset($this->polling[$id], $this->waiting[$id]);
			},
			function() use ($id) : Closure {
				$this->waiting[$id] = $this->polling[$id];
				unset($this->polling[$id]);
				return fn() => $this->wakeupQueue->enqueue($id);
			},
			$timeoutMicrosecond, $onTimeout
		);
		$this->polling[$id] = $task;
		return $task;
	}
}

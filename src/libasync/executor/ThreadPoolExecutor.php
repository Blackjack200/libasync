<?php

namespace libasync\executor;

use libasync\Promise;
use pocketmine\scheduler\ClosureTask;
use pocketmine\scheduler\TaskScheduler;

class ThreadPoolExecutor {
	private int $threadCount;
	/** @var Executor[] */
	private array $threads = [];
	private int $counter = 0;

	public function __construct(
		ThreadFactory $factory,
		TaskScheduler $scheduler,
		int           $threadCount,
	) {
		$this->threadCount = $threadCount;
		for ($i = 1; $i <= $threadCount; $i++) {
			$this->threads[] = $factory->new((string) $i);
		}
		$threads = $this->threads;
		$scheduler->scheduleRepeatingTask(new ClosureTask(static function () use ($threads) : void {
			foreach ($threads as $thread) {
				$thread->mainThreadHeartbeat();
			}
		}), 10);
	}

	public function getThreadCount() : int {
		return $this->threadCount;
	}

	public function start() : void {
		foreach ($this->threads as $thread) {
			$thread->start();
		}
	}

	public function shutdown() : void {
		foreach ($this->threads as $thread) {
			$thread->quit();
		}
	}

	public function submit(Promise $x) : void {
		if (++$this->counter >= $this->threadCount) {
			$this->counter = 0;
		}
		$this->threads[$this->counter]->submit($x);
	}

	public function mainThreadHeartbeat() : void {
		foreach ($this->threads as $thread) {
			$thread->mainThreadHeartbeat();
		}
	}
}
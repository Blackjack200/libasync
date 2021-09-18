<?php

namespace libasync\executor;

use libasync\Promise;

class ThreadPoolExecutor {
	private int $threadCount;
	/** @var Executor[] */
	private array $threads = [];
	private int $counter = 0;

	public function __construct(
		ThreadFactory $factory,
		int           $threadCount,
	) {
		$this->threadCount = $threadCount;
		for ($i = 1; $i <= $threadCount; $i++) {
			$this->threads[] = $factory->new((string) $i);
		}
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
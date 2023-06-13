<?php

namespace libasync\executor;

use Closure;
use libasync\promise\PromiseInterface;
use libasync\promise\task\AsyncPromiseTask;
use libasync\runtime\AsyncExecutionReceipt;
use libasync\runtime\AsyncRuntime;

class ThreadPoolExecutor implements AsyncRuntime {
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

	public function submit(PromiseInterface $x) : void {
		if (++$this->counter >= $this->threadCount) {
			$this->counter = 0;
		}
		AsyncPromiseTask::awaitRun($x, $this->threads[$this->counter]);
	}

	public function runAsync(Closure $closure, ?Closure $extraArgPrepareFunc = null, ?Closure $extraArgDestroyFunc = null, ?string $callTrace = null) : AsyncExecutionReceipt {
		if (++$this->counter >= $this->threadCount) {
			$this->counter = 0;
		}
		$e = $this->threads[$this->counter];
		return $e->runAsync($closure, $extraArgPrepareFunc, $extraArgDestroyFunc, $callTrace);
	}
}
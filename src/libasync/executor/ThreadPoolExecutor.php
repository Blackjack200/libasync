<?php

namespace libasync\executor;

use Closure;
use libasync\runtime\AsyncExecutionReceipt;
use libasync\runtime\AsyncRuntime;

class ThreadPoolExecutor implements AsyncRuntime {
	private int $threadCount;
	/** @var Executor[] */
	private array $threads = [];

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

	public function runAsync(Closure $closure, ?Closure $extraArgPrepareFunc = null, ?Closure $extraArgDestroyFunc = null, ?array $callTrace = null) : AsyncExecutionReceipt {
		$selectedThread = $this->threads[$this->threadCount - 1];
		$min = PHP_INT_MAX;
		foreach ($this->threads as $thread) {
			if ($thread->getPendingTaskCount() < $min) {
				$selectedThread = $thread;
				$min = $thread->getPendingTaskCount();
			}
		}
		return $selectedThread->runAsync($closure, $extraArgPrepareFunc, $extraArgDestroyFunc, $callTrace);
	}
}
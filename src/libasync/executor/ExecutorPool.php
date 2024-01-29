<?php

namespace libasync\executor;

use Closure;
use libasync\global\GlobalAsyncRuntime;
use libasync\runtime\AsyncExecutionEnvironment;
use libasync\runtime\AsyncExecutionReceipt;
use libasync\runtime\AsyncRuntime;
use libasync\utils\ClosureUtils;
use pocketmine\utils\Utils as PMMPUtils;

class ExecutorPool implements AsyncRuntime {
	private int $threadCount;
	/** @var ExecutorWorker[] */
	private array $workers = [];

	public function __construct(
		WorkerFactory $factory,
		int           $threadCount,
	) {
		$this->threadCount = $threadCount;
		for ($i = 1; $i <= $threadCount; $i++) {
			$this->workers[] = $factory->new((string) $i);
		}
		$loop = $this;
		GlobalAsyncRuntime::getLoop()->add(static fn() => $loop->collect());
	}

	public function start() : void {
		foreach ($this->workers as $thread) {
			$thread->start();
		}
	}

	public function shutdown() : void {
		foreach ($this->workers as $thread) {
			$thread->quit();
		}
	}

	public function getThreadCount() : int {
		return $this->threadCount;
	}

	public function runAsync(Closure $closure, ?AsyncExecutionEnvironment $env = null) : AsyncExecutionReceipt {
		ClosureUtils::validateThreadSafety($closure);
		$receipt = new AsyncExecutionReceipt();
		$receipt->setCallTrace(PMMPUtils::printableCurrentTrace());
		$task = new ExecutorWorkerTask($closure, $receipt, $env);
		$this->workers[mt_rand(1, $this->threadCount) - 1]->stack($task);
		return $receipt;
	}

	public function collect() : void {
		foreach ($this->workers as $worker) {
			$worker->collect();
			//\GlobalLogger::get()->debug("Still waiting");
		}
	}
}
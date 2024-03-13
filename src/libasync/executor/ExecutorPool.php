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
	private Closure $unregister;
	private int $threadCount;
	/** @var ExecutorWorker[] */
	private array $workers = [];
	private array $pendingTask = [];

	public function __construct(
		WorkerFactory $factory,
		int           $threadCount,
	) {
		$this->threadCount = $threadCount;
		for ($i = 1; $i <= $threadCount; $i++) {
			$this->workers[] = $factory->new((string) $i);
		}
		$loop = $this;
		$this->unregister = GlobalAsyncRuntime::getLoop()->add(static fn() => $loop->collect());
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
		($this->unregister)();
	}

	public function getThreadCount() : int {
		return $this->threadCount;
	}

	public function runAsync(Closure $closure, ?AsyncExecutionEnvironment $env = null) : AsyncExecutionReceipt {
		ClosureUtils::validateThreadSafety($closure);
		$receipt = new AsyncExecutionReceipt();
		$receipt->setCallTrace(PMMPUtils::printableCurrentTrace());
		$this->pendingTask[] = new ExecutorWorkerTask($receipt, $closure, $env);
		return $receipt;
	}

	public function collect() : void {
		foreach ($this->workers as $worker) {
			if ($worker->getStacked() === 0) {
				$shift = array_shift($this->pendingTask);
				if ($shift !== null) {
					$worker->stack($shift);
				} else {
					break;
				}
			}
		}
		foreach ($this->workers as $worker) {
			$worker->autoCollect();
			//\GlobalLogger::get()->debug("Still waiting");
		}
	}
}
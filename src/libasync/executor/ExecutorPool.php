<?php

namespace libasync\executor;

use Closure;
use libasync\global\GlobalAsyncRuntime;
use libasync\runtime\AsyncExecutionEnvironment;
use libasync\runtime\AsyncExecutionReceipt;
use libasync\runtime\AsyncRuntime;
use libasync\utils\ClosureUtils;
use pocketmine\snooze\SleeperHandler;
use pocketmine\utils\Utils as PMMPUtils;

class ExecutorPool implements AsyncRuntime {
	private Closure $unregister;
	private int $threadCount;
	/** @var ExecutorWorker[] */
	private array $workers = [];
	/** @var int[] */
	private array $notifiers = [];
	/** @var ExecutorWorkerTask[] */
	private array $pendingTask = [];

	public function __construct(
		private readonly SleeperHandler $handler,
		WorkerFactory $factory,
		int           $threadCount,
	) {
		$this->threadCount = $threadCount;
		for ($i = 1; $i <= $threadCount; $i++) {
			$worker = $factory->new((string) $i);
			assert($worker instanceof ExecutorWorker);
			$entry = $this->handler->addNotifier(fn() => $worker->autoCollect());
			$this->notifiers[] = $entry->getNotifierId();
			$worker->setSleeperHandlerEntry($entry);
			$this->workers[] = $worker;
		}
		$this->unregister = GlobalAsyncRuntime::getLoop()->add(fn() => $this->collect());
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
		foreach ($this->notifiers as $notifier) {
			$this->handler->removeNotifier($notifier);
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
		}
	}
}
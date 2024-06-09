<?php

namespace libasync\executor;

use Closure;
use libasync\runtime\AsyncExecutionEnvironment;
use libasync\runtime\AsyncExecutionReceipt;
use libasync\runtime\AsyncRuntime;
use libasync\utils\ClosureUtils;
use pocketmine\snooze\SleeperHandler;
use pocketmine\utils\Utils as PMMPUtils;

class ExecutorPool implements AsyncRuntime {
	private int $threadCount;
	/** @var ExecutorWorker[] */
	private array $workers = [];
	/** @var int[] */
	private array $notifiers = [];

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
	}

	public function getThreadCount() : int {
		return $this->threadCount;
	}

	public function runAsync(Closure $closure, ?AsyncExecutionEnvironment $env = null) : AsyncExecutionReceipt {
		ClosureUtils::validateThreadSafety($closure);
		$receipt = new AsyncExecutionReceipt();
		$receipt->setCallTrace(PMMPUtils::printableCurrentTrace());
		usort($this->workers, static fn(ExecutorWorker $a, ExecutorWorker $b) => $a->getStacked() <=> $b->getStacked());
		$this->workers[0]->stack(new ExecutorWorkerTask($receipt, $closure, $env));
		return $receipt;
	}

	public function collect() : void {
		foreach ($this->workers as $worker) {
			$worker->autoCollect();
		}
	}
}
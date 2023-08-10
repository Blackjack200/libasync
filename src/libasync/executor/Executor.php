<?php

namespace libasync\executor;

use Closure;
use GlobalLogger;
use libasync\exception\ExecutionExceptionWrapper;
use libasync\runtime\AsyncExecutionEnvironment;
use libasync\runtime\AsyncExecutionReceipt;
use libasync\runtime\AsyncRuntime;
use libasync\utils\ClosureUtils;
use pmmp\thread\ThreadSafeArray;
use pocketmine\thread\log\ThreadSafeLogger;
use pocketmine\thread\Thread;
use pocketmine\utils\Utils as PMMPUtils;
use ReflectionClass;
use Throwable;

final class Executor extends Thread implements AsyncRuntime {
	private readonly ThreadSafeArray $queue;
	private bool $readyToUse = false;
	private bool $initialized = false;

	public function __construct(
		private readonly ThreadSafeLogger           $logger,
		private readonly string                     $autoload,
		private readonly ?AsyncExecutionEnvironment $env,
	) {
		$this->queue = new ThreadSafeArray();
	}

	public function isReadyToUse() : bool { return $this->synchronized(fn() => $this->readyToUse); }

	public function isInitialized() : bool { return $this->synchronized(fn() => $this->initialized); }

	public function getPendingTaskCount() : int { return $this->synchronized(fn() => count($this->queue)); }

	private function setError(AsyncExecutionReceipt $rec, Throwable $err) : void {
		$rec->setError(ExecutionExceptionWrapper::wrap($err));
	}

	protected function onRun() : void {
		if ($this->autoload !== '') {
			require_once $this->autoload;
		}
		GlobalLogger::set($this->logger);
		GlobalLogger::get()->debug(((new ReflectionClass($this))->getShortName()) . ' started');
		try {
			if ($this->env !== null) {
				$args = $this->env->prepareArgs();
			} else {
				$args = [];
			}
			$this->synchronized(fn() => $this->initialized = true);
			$this->synchronized(fn() => $this->readyToUse = true);
			while (!$this->isKilled) {
				$this->runTasks($args);
				$this->synchronized(fn() => $this->wait(1000));
			}
			$this->runTasks($args);
			if ($this->env !== null) {
				$this->env->releaseArgs($args);
			}
			GlobalLogger::get()->debug(((new ReflectionClass($this))->getShortName()) . ' shutdown gracefully');
		} finally {
			$this->synchronized(fn() => $this->initialized = true);
		}
	}

	public function runAsync(Closure $closure, ?AsyncExecutionEnvironment $env = null) : AsyncExecutionReceipt {
		ClosureUtils::validateThreadSafety($closure);
		$rec = new AsyncExecutionReceipt();
		$rec->setCallTrace(PMMPUtils::printableCurrentTrace());
		$data = ThreadSafeArray::fromArray([$rec, $closure, $env]);
		$this->synchronized(function() use ($data) : void {
			$this->queue[] = $data;
			$this->notify();
		});
		return $rec;
	}

	private function runTask(AsyncExecutionReceipt $rec, Closure $closure, ?AsyncExecutionEnvironment $env, array $injected) : void {
		try {
			try {
				if ($env !== null) {
					$result = $env->run($closure, $injected);
				} else {
					$result = $closure(...$injected);
				}
				$rec->setResult($result);
			} catch (Throwable $err) {
				$this->setError($rec, $err);
			}
		} catch (Throwable $err) {
			$this->setError($rec, $err);
		}
	}

	private function runTasks(array $injected) : void {
		$runCount = 0;
		while (!$this->isKilled && $this->synchronized(fn() => $this->queue->count()) > 0) {
			$runCount++;
			[$rec, $closure, $env] = $this->synchronized(fn() => $this->queue->shift());
			assert($rec instanceof AsyncExecutionReceipt);
			assert($closure instanceof Closure);
			assert($env instanceof AsyncExecutionEnvironment || $env === null);
			$this->runTask($rec, $closure, $env, $injected);
		}
		if ($runCount > 0 && random_int(0, 2) === 0) {
			gc_enable();
			gc_collect_cycles();
			gc_mem_caches();
		}
	}
}
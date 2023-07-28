<?php

namespace libasync\executor;

use Closure;
use GlobalLogger;
use libasync\exception\AsyncExecutionException;
use libasync\runtime\AsyncExecutionReceipt;
use libasync\runtime\AsyncRuntime;
use libasync\utils\Utils;
use pmmp\thread\ThreadSafeArray;
use pocketmine\thread\log\ThreadSafeLogger;
use pocketmine\thread\Thread;
use pocketmine\utils\Utils as PMMPUtils;
use ReflectionClass;
use Throwable;

final class Executor extends Thread implements AsyncRuntime {
	public readonly string $autoload;
	protected readonly ThreadSafeArray $queue;
	protected readonly ThreadSafeLogger $logger;
	protected readonly Closure $defer;
	protected readonly Closure $prepareArgs;
	protected bool $readyToUse = false;
	protected bool $initialized = false;

	public function __construct(
		ThreadSafeLogger $logger,
		string           $autoload,
		ThreadSafeArray  $queue,
		Closure          $prepareArgs,
		Closure          $defer,
	) {
		$this->prepareArgs = $prepareArgs;
		$this->defer = $defer;
		$this->logger = $logger;
		$this->autoload = $autoload;
		$this->queue = $queue;
	}

	public function isReadyToUse() : bool { return $this->synchronized(fn() => $this->readyToUse); }

	public function isInitialized() : bool { return $this->synchronized(fn() => $this->initialized); }

	private function setError(AsyncExecutionReceipt $rec, Throwable $err) : void {
		$rec->setError(AsyncExecutionException::wrap($err));
	}

	protected function onRun() : void {
		if ($this->autoload !== '') {
			require_once $this->autoload;
		}
		GlobalLogger::set($this->logger);
		GlobalLogger::get()->debug(((new ReflectionClass($this))->getShortName()) . ' started');
		try {
			$args = ($this->prepareArgs)($this) ?? [];
			$this->synchronized(fn() => $this->initialized = true);
			$this->synchronized(fn() => $this->readyToUse = true);
			while (!$this->isKilled) {
				$this->runTasks(...$args);
				$this->synchronized(fn() => $this->wait(1000));
			}
			($this->defer)(...$args);
			GlobalLogger::get()->debug(((new ReflectionClass($this))->getShortName()) . ' shutdown gracefully');
		} finally {
			$this->synchronized(fn() => $this->initialized = true);
		}
	}

	public function runAsync(Closure $closure, ?Closure $extraArgPrepareFunc = null, ?Closure $extraArgDestroyFunc = null, ?array $callTrace = null) : AsyncExecutionReceipt {
		$rec = new AsyncExecutionReceipt();
		$rec->setCallTrace($callTrace ?? PMMPUtils::printableCurrentTrace());
		$this->synchronized(fn() => $this->queue[] = ThreadSafeArray::fromArray([$rec, $closure, $extraArgPrepareFunc, $extraArgDestroyFunc]));
		$this->synchronized(fn() => $this->notify());
		return $rec;
	}

	private function runTask(AsyncExecutionReceipt $rec, ?Closure $closure, array $argsInjected, ?Closure $extraArgPrepareFunc, ?Closure $extraArgDestroyFunc) : void {
		try {
			$args = $extraArgPrepareFunc !== null ? ($extraArgPrepareFunc)($rec) : [];
			try {
				$result = $closure(...$args, ...$argsInjected);
				$rec->setResult($result);
			} catch (Throwable $err) {
				$this->setError($rec, $err);
			}
			if ($extraArgDestroyFunc !== null) {
				($extraArgDestroyFunc)(...$args);
			}
		} catch (Throwable $err) {
			$this->setError($rec, $err);
		}
	}

	private function runTasks(...$argsInjected) : void {
		$runCount = 0;
		while (!$this->isKilled && $this->synchronized(fn() => $this->queue->count()) > 0) {
			$runCount++;
			[$rec, $closure, $extraArgPrepareFunc, $extraArgDestroyFunc] = $this->synchronized(fn() => $this->queue->shift());
			assert($rec instanceof AsyncExecutionReceipt && $closure instanceof Closure);
			$this->runTask($rec, $closure, $argsInjected, $extraArgPrepareFunc, $extraArgDestroyFunc);
		}
		if ($runCount > 0 && random_int(0, 2) === 0) {
			gc_enable();
			gc_collect_cycles();
			gc_mem_caches();
		}
	}
}
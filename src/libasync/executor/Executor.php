<?php

namespace libasync\executor;

use Closure;
use GlobalLogger;
use libasync\exception\AsyncExecutionException;
use libasync\runtime\AsyncExecutionRecipient;
use libasync\runtime\AsyncRuntime;
use pmmp\thread\ThreadSafeArray;
use pocketmine\thread\log\ThreadSafeLogger;
use pocketmine\thread\Thread;
use pocketmine\utils\Utils;
use ReflectionClass;
use Throwable;

class Executor extends Thread implements AsyncRuntime {
	public string $autoload;
	protected ThreadSafeArray $queue;
	protected ThreadSafeLogger $logger;
	protected Closure $defer;
	protected Closure $prepareArgs;

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

	public function log(?string $val) : void {
		if ($val !== null) {
			GlobalLogger::get()->debug($val);
		}
	}

	protected function executeTasks(...$argsInjected) : void {
		while ($this->queue->synchronized(fn() => $this->queue->count()) > 0) {
			[$reci, $closure, $extraArgPrepareFunc, $extraArgDestroyFunc] = $this->queue->synchronized(fn() => $this->queue->shift());
			assert($reci instanceof AsyncExecutionRecipient && $closure instanceof Closure);
			try {
				if ($extraArgPrepareFunc !== null) {
					$args = ($extraArgPrepareFunc)($reci);
				} else {
					$args = [];
				}
				try {
					$result = ($closure)(...$args, ...$argsInjected);
					$reci->setResult($result);
				} catch (Throwable $err) {
					$this->setError($reci, $err);
				}
				if ($extraArgDestroyFunc !== null) {
					($extraArgDestroyFunc)(...$args);
				}
			} catch (Throwable $err) {
				$this->setError($reci, $err);
			}
		}
	}

	private function setError(AsyncExecutionRecipient $reci, Throwable $err) : void {
		$reci->setError(AsyncExecutionException::from(ThreadSafeArray::fromArray([$err::class, $err->getMessage(), igbinary_serialize(Utils::printableTrace($err->getTrace())), $err->getCode(), $err->getFile(), $err->getLine()])));
	}

	protected function onRun() : void {
		GlobalLogger::set($this->logger);
		if ($this->autoload !== '') {
			require_once $this->autoload;
		}
		GlobalLogger::get()->debug(((new ReflectionClass($this))->getShortName()) . ' started');
		$tick = 0;
		$args = ($this->prepareArgs)($this) ?? [];
		while (!$this->isKilled) {
			$this->executeTasks(...$args);
			usleep(10);
			if ($tick++ === 200) {
				gc_enable();
				gc_collect_cycles();
				gc_mem_caches();
				$tick = 0;
			}
		}
		($this->defer)(...$args);
		GlobalLogger::get()->debug(((new ReflectionClass($this))->getShortName()) . ' shutdown gracefully');
	}

	public function runAsync(Closure $closure, ?Closure $extraArgPrepareFunc = null, ?Closure $extraArgDestroyFunc = null, ?string $callTrace = null) : AsyncExecutionRecipient {
		$reci = new AsyncExecutionRecipient();
		$reci->setCallTrace($callTrace ?? \libasync\utils\Utils::smartSerialize(Utils::printableCurrentTrace()));
		$this->queue->synchronized(fn() => $this->queue[] = ThreadSafeArray::fromArray([$reci, $closure, $extraArgPrepareFunc, $extraArgDestroyFunc]));
		return $reci;
	}
}
<?php

namespace libasync\executor;

use Closure;
use GlobalLogger;
use libasync\promise\BasePromiseRuntime;
use libasync\promise\PromiseInterface;
use Logger;
use pocketmine\thread\Thread;
use ReflectionClass;
use Threaded;
use Volatile;

class Executor extends Thread {
	/** @var PromiseInterface[] */
	private static array $promiseThreadLocal = [];
	public string $autoload;
	protected Threaded $queue;
	protected Threaded $finished;
	protected Logger $logger;
	protected Closure $defer;
	protected Closure $prepareArgs;

	public function __construct(
		Logger   $logger,
		string   $autoload,
		Volatile $queue,
		Closure  $prepareArgs,
		Closure  $defer,
	) {
		$this->prepareArgs = $prepareArgs;
		$this->defer = $defer;
		$this->logger = $logger;
		$this->autoload = $autoload;
		$this->queue = $queue;
		$this->finished = new Threaded();
	}

	public function log(?string $val) : void {
		if ($val !== null) {
			GlobalLogger::get()->debug($val);
		}
	}

	public function mainThreadHeartbeat() : void {
		while (($data = $this->finished->synchronized(fn() => $this->finished->shift())) !== null) {
			[$hash, $runtime] = igbinary_unserialize($data);
			assert($runtime instanceof BasePromiseRuntime);
			$promise = self::$promiseThreadLocal[$hash];
			unset(self::$promiseThreadLocal[$hash]);
			$runtime->onFinished($promise);
		}
	}

	public function submit(PromiseInterface $promise) : void {
		$hash = spl_object_hash($promise);
		self::$promiseThreadLocal[$hash] = $promise;
		$runtime = new BasePromiseRuntime();
		$runtime->setup();
		$this->queue->synchronized(fn() => $this->queue[] = [$promise->getAsyncCall(), $runtime, $hash]);
		$this->notify();
	}

	protected function executeTasks(...$args) : void {
		while ($this->queue->synchronized(fn() => $this->queue->count()) > 0) {
			[$cal, $runtime, $hash] = $this->queue->synchronized(fn() => $this->queue->shift());
			assert($cal instanceof Closure);
			assert($runtime instanceof BasePromiseRuntime);
			assert(is_string($hash));
			$runtime->runFunc($cal, ...$args);
			$this->finished->synchronized(fn() => $this->finished[] = igbinary_serialize([$hash, $runtime]));
		}
	}

	protected function onRun() : void {
		GlobalLogger::set($this->logger);
		if ($this->autoload !== '') {
			require_once $this->autoload;
		}
		GlobalLogger::get()->debug(((new ReflectionClass($this))->getShortName()) . ' started');
		$tick = 0;
		$args = ($this->prepareArgs)($this);
		while (!$this->isKilled) {
			$this->executeTasks(...$args);
			usleep(50);
			if ($tick++ === 60000) {
				gc_enable();
				gc_collect_cycles();
				gc_mem_caches();
				$tick = 0;
			}
		}
		($this->defer)(...$args);
		GlobalLogger::get()->debug(((new ReflectionClass($this))->getShortName()) . ' shutdown gracefully');
	}
}
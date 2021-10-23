<?php

namespace libasync\executor;

use Closure;
use GlobalLogger;
use libasync\InterruptSignal;
use libasync\Promise;
use libasync\PromiseException;
use Logger;
use pocketmine\thread\Thread;
use pocketmine\utils\Utils;
use ReflectionClass;
use Threaded;
use Throwable;
use Volatile;

class Executor extends Thread {
	private static array $promiseMap = [];
	public string $autoload;
	protected Threaded $queue;
	protected Threaded $finished;
	protected Logger $logger;
	protected Closure $defer;
	protected Closure $prepareArgs;

	public function __construct(
		Logger $logger,
		string $autoload,
		Volatile $queue,
		Closure $prepareArgs,
		Closure $defer,
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
		$this->finished->synchronized(function () : void {
			while ($this->finished->count() > 0) {
				[$hash, $err, $rejected, $result] = igbinary_unserialize($this->finished->shift());
				/** @var Promise $promise */
				$promise = self::$promiseMap[$hash];
				$this->executePromiseCallbacks($promise, $err, $rejected, $result);
				unset(self::$promiseMap[$hash]);
			}
		});
	}

	protected function executePromiseCallbacks(Promise $promise, ?PromiseException $err, bool $rejected, mixed $result) : void {
		if ($err !== null) {
			$errorHandler = $promise->getErrorHandler();
			if ($errorHandler !== null) {
				$errorHandler($err);
			}
			return;
		}
		if ($rejected) {
			$callbacks = $promise->getRejectedCallbacks();
		} else {
			$callbacks = $promise->getFulfillCallbacks();
		}
		foreach ($callbacks as $callback) {
			$callback(...igbinary_unserialize($result));
		}
	}

	public function submit(Promise $promise) : void {
		$hash = spl_object_hash($promise);
		self::$promiseMap[$hash] = $promise;
		$this->queue->synchronized(fn() => $this->queue[] = [$promise->getAsyncCall(), $hash]);
		$this->notify();
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

	protected function executeTasks(...$args) : void {
		$result = null;
		$rejected = true;
		$reject = static function (...$reason) use (&$result) : void {
			$result = igbinary_serialize($reason);
			throw new InterruptSignal();
		};
		$err = null;
		$resolve = static function (...$reason) use (&$rejected, &$result) : void {
			$result = igbinary_serialize($reason);
			$rejected = false;
			throw new InterruptSignal();
		};
		while ($this->queue->synchronized(fn() => $this->queue->count()) > 0) {
			[$cal, $hash] = $this->queue->synchronized(fn() => $this->queue->shift());
			try {
				$cal($resolve, $reject, ...$args);
			} catch (Throwable $e) {
				if (!$e instanceof InterruptSignal) {
					$err = PromiseException::from([$e::class, $e->getMessage(), Utils::printableTrace($e->getTrace()), $e->getCode(), $e->getFile(), $e->getLine()]);
				}
			}
			$this->finished->synchronized(fn() => $this->finished[] = igbinary_serialize([$hash, $err, $rejected, $result]));
		}
	}
}
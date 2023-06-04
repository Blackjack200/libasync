<?php

namespace libasync\promise;

use Closure;
use GlobalLogger;
use libasync\InterruptSignal;
use libasync\utils\ArgInfo;
use Logger;
use pmmp\thread\ThreadSafe;
use pmmp\thread\ThreadSafeArray;
use pocketmine\utils\Utils;
use Throwable;

class BasePromiseRuntime extends ThreadSafe {
	protected string $result;
	protected bool $rejected = true;
	protected ?PromiseException $error = null;
	private ThreadSafeArray $callTrace;

	public function __construct(private bool $serialize = true) { }

	public function runFunc(Closure $cal, ...$args) : void {
		$this->result = igbinary_serialize([]);
		$reject = function(...$reason) : void {
			$this->rejected = true;
			$this->result = $this->serializeData($reason);
			$this->onReject(...$reason);
			throw new InterruptSignal();
		};
		$resolve = function(...$result) : void {
			$this->rejected = false;
			$this->result = $this->serializeData($result);
			$this->onResolve(...$result);
			throw new InterruptSignal();
		};
		$errorHandler = $this->getErrorHandler();
		$extraArgs = $this->getExtraArgs();
		try {
			$cal($resolve, $reject, ...array_map(static function(ArgInfo $info) {
				return $info->value;
			}, $extraArgs), ...$args);
		} catch (Throwable $err) {
			$errorHandler($err);
		}
		foreach ($extraArgs as $arg) {
			($arg->func)();
		}
		unset($this->cal);
	}

	protected function serializeData($val) : string {
		if ($this->serialize) {
			return igbinary_serialize($val);
		}
		return $val;
	}

	protected function onReject(...$result) : void { }

	protected function onResolve(...$result) : void { }

	public function getErrorHandler() : Closure {
		return function(Throwable $err) : void {
			if (!$err instanceof InterruptSignal) {
				$this->error = PromiseException::from(ThreadSafeArray::fromArray([$err::class, $err->getMessage(), ThreadSafeArray::fromArray(Utils::printableTrace($err->getTrace())), $err->getCode(), $err->getFile(), $err->getLine()]));
			}
		};
	}

	/** @return ArgInfo[] */
	protected function getExtraArgs() : array { return []; }

	final public function onFinished(PromiseInterface $promise) : void {
		$logger = GlobalLogger::get();
		if ($this->error !== null) {
			$handler = $promise->getErrorHandler();
			if ($handler !== null) {
				$handler($this->error);
			} else {
				$this->error->print($logger);
				$this->printCause($logger);
			}
			return;
		}
		try {
			$data = $this->deserializeData($this->result);
			if ($this->rejected) {
				$callbacks = $promise->getRejectedCallbacks();
			} else {
				$callbacks = $promise->getFulfillCallbacks();
			}
			foreach ($callbacks as $callback) {
				if (is_iterable($data)) {
					$callback(...$data);
				} else {
					$callback($data);
				}
			}
		} catch (Throwable $throwable) {
			$logger->logException($throwable);
			$this->printCause($logger);
		}
	}

	protected function printCause(Logger $logger) : void {
		$logger->critical("caused by");
		foreach ($this->callTrace as $trace) {
			$logger->critical($trace);
		}
	}

	protected function deserializeData($val) {
		if ($this->serialize) {
			return igbinary_unserialize($val);
		}
		return $val;
	}

	public function setup() : void { $this->callTrace = ThreadSafeArray::fromArray(Utils::printableCurrentTrace()); }
}
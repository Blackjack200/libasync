<?php

namespace libasync;

use Closure;
use GlobalLogger;
use pocketmine\utils\Utils;
use Throwable;

trait PromiseRuntime {
	protected string $result;
	protected bool $rejected = true;
	protected ?PromiseException $error = null;

	protected function runFunc(Closure $cal) : void {
		$reject = function(...$reason) : void {
			$this->result = $this->serializeData($reason);
			throw new InterruptSignal();
		};
		$resolve = function(...$reason) : void {
			$this->rejected = false;
			$this->result = $this->serializeData($reason);
			throw new InterruptSignal();
		};
		$errorHandler = function(Throwable $err) : void {
			if (!$err instanceof InterruptSignal) {
				$this->error = PromiseException::from([$err::class, $err->getMessage(), Utils::printableTrace($err->getTrace()), $err->getCode(), $err->getFile(), $err->getLine()]);
			}
		};
		$args = $this->getExtraArgs();
		try {
			$cal($resolve, $reject, ...array_map(static function(ArgInfo $info) {
				return $info->value;
			}, $args));
		} catch (Throwable $err) {
			$errorHandler($err);
		}
		foreach ($args as $arg) {
			($arg->func)();
		}
		unset($this->cal);
	}

	private function deserializeData($val) {
		return igbinary_unserialize($val);
	}

	private function serializeData($val) : string {
		return igbinary_serialize($val);
	}

	/** @return ArgInfo[] */
	protected function getExtraArgs() : array {
		return [];
	}

	final protected function onFinished(PromiseInterface $promise) : void {
		if ($this->error !== null) {
			$handler = $promise->getErrorHandler();
			if ($handler !== null) {
				$handler($this->error);
			} else {
				$this->error->print(GlobalLogger::get());
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
			GlobalLogger::get()->logException($throwable);
		}
	}
}
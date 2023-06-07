<?php

namespace libasync\result;


use GlobalLogger;
use libasync\exception\AsyncExecutionException;
use libasync\InterruptSignal;
use libasync\promise\PromiseInterface;
use pmmp\thread\ThreadSafeArray;
use pocketmine\utils\Utils;

class ResultPromiseCaller {
	private mixed $result;
	private bool $rejected = true;
	private ?AsyncExecutionException $error = null;
	protected PromiseInterface $promise;

	public function __construct(PromiseInterface $promise) {
		$this->promise = $promise;
	}

	private function onRun() : void {
		$reject = function(...$reason) : void {
			$this->result = $reason;
			$this->onCompletion();
			throw new InterruptSignal();
		};
		$resolve = function(...$reason) : void {
			$this->rejected = false;
			$this->result = $reason;
			$this->onCompletion();
			throw new InterruptSignal();
		};
		$errorHandler = function(\Throwable $err) : void {
			if (!$err instanceof InterruptSignal) {
				$this->error = AsyncExecutionException::from(ThreadSafeArray::fromArray([$err::class, $err->getMessage(), igbinary_serialize(Utils::printableTrace($err->getTrace())), $err->getCode(), $err->getFile(), $err->getLine()]));
			}
		};
		($this->promise->getAsyncCall())($resolve, $reject, $errorHandler);
	}

	private function onCompletion() : void {
		$promise = $this->promise;
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
			$data = $this->result;
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
		} catch (\Throwable $throwable) {
			GlobalLogger::get()->logException($throwable);
		}
	}

	final public function start() : void {
		$this->onRun();
	}
}

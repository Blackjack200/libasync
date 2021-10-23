<?php

namespace libasync;

use Closure;
use GlobalLogger;
use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;
use pocketmine\utils\AssumptionFailedError;
use pocketmine\utils\Utils;
use Throwable;
use function igbinary_serialize;
use function igbinary_unserialize;

class AsyncPromiseTask extends AsyncTask {
	protected Closure $cal;
	protected string $result;
	protected bool $rejected = true;
	protected ?PromiseException $error = null;

	//protected string $backtrace;

	public function __construct(PromiseInterface $promise) {
		$this->cal = $promise->getAsyncCall();
		$this->storeLocal('promise', $promise);
		/*ob_start();
		debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
		$this->backtrace = ob_get_clean();*/
	}

	final public function onRun() : void {
		$reject = function (...$reason) : void {
			$this->result = $this->serializeData($reason);
			throw new InterruptSignal();
		};
		$resolve = function (...$reason) : void {
			$this->rejected = false;
			$this->result = $this->serializeData($reason);
			throw new InterruptSignal();
		};
		$args = $this->getExtraArgs();
		try {
			($this->cal)($resolve, $reject, ...array_map(static function ($info) {
				if (!$info instanceof ArgInfo) {
					throw new AssumptionFailedError('The extra args should wrapped by ArgInfo');
				}
				return $info->value;
			}, $args));
		} catch (Throwable $err) {
			if (!$err instanceof InterruptSignal) {
				$this->error = PromiseException::from([$err::class, $err->getMessage(), Utils::printableTrace($err->getTrace()), $err->getCode(), $err->getFile(), $err->getLine()]);
			}
		}
		foreach ($args as $arg) {
			($arg->func)();
		}
		unset($this->cal);
	}

	protected function serializeData($val) : string {
		return igbinary_serialize($val);
	}

	/** @return ArgInfo[] */
	protected function getExtraArgs() : array {
		return [];
	}

	final public function onCompletion() : void {
		$promise = $this->fetchLocal('promise');
		if (!$promise instanceof PromiseInterface) {
			throw new AssumptionFailedError('ThreadLocal should return Promise back.');
		}
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

	final protected function deserializeData($val) {
		return igbinary_unserialize($val);
	}

	final public function start() : void {
		Server::getInstance()->getAsyncPool()->submitTask($this);
	}
}
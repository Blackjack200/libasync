<?php


namespace libasync;


use GlobalLogger;
use pocketmine\entity\projectile\Throwable;
use pocketmine\scheduler\Task;
use pocketmine\utils\AssumptionFailedError;
use pocketmine\utils\Utils;

class SyncedPromiseTask extends Task {
	protected mixed $result;
	protected bool $rejected = true;
	protected ?PromiseException $error = null;
	protected SyncedPromise $promise;

	public function __construct(SyncedPromise $promise) {
		$this->promise = $promise;
	}

	final public function onRun() : void {
		$reject = function (...$reason) : void {
			$this->result = $reason;
			throw new InterruptSignal();
		};
		$resolve = function (...$reason) : void {
			$this->rejected = false;
			$this->result = $reason;
			throw new InterruptSignal();
		};
		$args = $this->getExtraArgs();
		try {
			($this->promise->getAsyncCall())($resolve, $reject, ...array_map(static function ($info) {
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
	}

	/** @return ArgInfo[] */
	protected function getExtraArgs() : array {
		return [];
	}

	final public function onCompletion() : void {
		if ($this->error !== null) {
			$handler = $this->promise->getErrorHandler();
			if ($handler !== null) {
				$handler($this->error);
			} else {
				$this->error->print(GlobalLogger::get());
			}
			return;
		}
		if ($this->rejected) {
			$callbacks = $this->promise->getRejectedCallbacks();
		} else {
			$callbacks = $this->promise->getFulfillCallbacks();
		}
		$data = $this->result;
		foreach ($callbacks as $callback) {
			if (is_iterable($data)) {
				$callback(...$data);
			} else {
				$callback($data);
			}
		}
	}

	final public function start() : void {
		AsyncLoader::getInstance()->getScheduler()->scheduleTask($this);
	}
}
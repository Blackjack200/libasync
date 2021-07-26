<?php

namespace libasync;

use GlobalLogger;
use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;
use pocketmine\utils\AssumptionFailedError;
use Throwable;
use function igbinary_serialize;
use function igbinary_unserialize;

class PromiseAsyncTask extends AsyncTask {
	/** @var callable */
	protected $cal;
	protected string $result;
	protected bool $rejected = false;

	public function __construct(PromiseInterface $promise) {
		$this->cal = $promise->getAsyncCall();
		$this->storeLocal('promise', $promise);
	}

	final public function onRun() : void {
		$reject = function (...$reason) : void {
			$this->rejected = true;
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
				$this->rejected = true;
				$this->result = $this->serializeData([get_class($err), $err->getMessage()]);
				GlobalLogger::get()->logException($err);
			}
		}
		foreach ($args as $arg) {
			($arg->finalizeFunction)();
		}
		$this->cal = null;
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
		$data = $this->deserializeData($this->result);
		if ($this->rejected) {
			$callbacks = $promise->getRejectedCallbacks();
		} else {
			$callbacks = $promise->getFulfillCallbacks();
		}
		foreach ($callbacks as $callback) {
			$callback(...$data);
		}
	}

	/**
	 * @return mixed|null
	 */
	final protected function deserializeData(string $val) {
		return igbinary_unserialize($val);
	}

	final public function start() : void {
		Server::getInstance()->getAsyncPool()->submitTask($this);
	}
}
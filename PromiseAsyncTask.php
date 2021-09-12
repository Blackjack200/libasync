<?php

namespace libasync;

use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;
use pocketmine\utils\AssumptionFailedError;
use pocketmine\utils\Utils;
use Throwable;
use function igbinary_serialize;
use function igbinary_unserialize;

/** @template T */
class PromiseAsyncTask extends AsyncTask {
	/** @var callable */
	protected $cal;
	protected string $result;
	protected bool $rejected = true;
	protected PromiseException $error;

	public function __construct(PromiseInterface $promise) {
		$this->cal = $promise->getAsyncCall();
		$this->storeLocal('promise', $promise);
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
			($arg->finalizeFunction)();
		}
		$this->cal = null;
	}

	/** @param T $val */
	protected function serializeData(mixed $val) : string {
		return igbinary_serialize($val);
	}

	/**
	 * @return T
	 */
	final protected function deserializeData(string $val) {
		return igbinary_unserialize($val);
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
		if (isset($this->error)) {
			$promise->getErrorHandler()($this->error);
			return;
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

	final public function start() : void {
		Server::getInstance()->getAsyncPool()->submitTask($this);
	}
}
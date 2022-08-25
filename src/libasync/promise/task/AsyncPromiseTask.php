<?php

namespace libasync\promise\task;

use Closure;
use libasync\promise\BasePromiseRuntime;
use libasync\promise\PromiseInterface;
use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;
use pocketmine\utils\AssumptionFailedError;

class AsyncPromiseTask extends AsyncTask {
	protected Closure $cal;
	protected BasePromiseRuntime $runtime;

	public function __construct(PromiseInterface $promise) {
		$this->cal = $promise->getAsyncCall();
		$this->storeLocal('promise', $promise);
		$this->runtime = new BasePromiseRuntime();
		$this->runtime->setup();
	}

	final public function onRun() : void {
		$this->runtime->runFunc($this->cal);
	}

	final public function start() : void {
		Server::getInstance()->getAsyncPool()->submitTask($this);
	}

	public function onCompletion() : void {
		$promise = $this->fetchLocal('promise');
		if (!$promise instanceof PromiseInterface) {
			throw new AssumptionFailedError('ThreadLocal should return Promise back.');
		}
		$this->runtime->onFinished($promise);
	}
}
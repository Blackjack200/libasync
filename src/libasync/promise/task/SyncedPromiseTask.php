<?php


namespace libasync\promise\task;


use libasync\AsyncLoader;
use libasync\promise\BasePromiseRuntime;
use libasync\promise\PromiseInterface;
use pocketmine\scheduler\Task;

class SyncedPromiseTask extends Task {
	protected PromiseInterface $promise;
	protected BasePromiseRuntime $runtime;

	public function __construct(PromiseInterface $promise) {
		$this->promise = $promise;
		$this->runtime = new BasePromiseRuntime();
		$this->runtime->setup();
	}

	public function onRun() : void {
		$this->runtime->runFunc($this->promise->getAsyncCall());
	}

	final public function onCompletion() : void {
		$this->runtime->onFinished($this->promise);
	}

	final public function start() : void {
		AsyncLoader::getInstance()->getScheduler()->scheduleTask($this);
	}
}
<?php


namespace libasync;


use pocketmine\scheduler\Task;

class SyncedPromiseTask extends Task {
	protected PromiseInterface $promise;
	use PromiseRuntime;

	public function __construct(PromiseInterface $promise) {
		$this->promise = $promise;
	}

	public function onRun() : void {
		$this->runFunc($this->promise->getAsyncCall());
	}

	final public function onCompletion() : void {
		$this->onFinished($this->promise);
	}

	final public function start() : void {
		AsyncLoader::getInstance()->getScheduler()->scheduleTask($this);
	}
}
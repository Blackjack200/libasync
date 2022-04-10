<?php

namespace libasync;

use pocketmine\scheduler\Task;

class PromiseRaceTask extends Task {
	private Promise $master;
	/** @var PromiseInterface[] */
	public array $promises = [];
	private bool $settled = false;
	private bool|null $resolved = null;

	public function __construct(Promise $master) {
		$this->master = $master;
	}

	public function onRun() : void {
		if (!$this->settled) {
			$this->master->getAsyncCall()($this);
			foreach ($this->promises as $promise) {
				$promise->whenFulfill(function() : void {
					$this->resolved = true;
				})->whenReject(function() : void {
					$this->resolved = false;
				})->catch(function() : void {
					$this->resolved = false;
				})->settle();
			}
			$this->settled = true;
			return;
		}
		if ($this->resolved !== null) {
			$this->getHandler()->cancel();
			if ($this->resolved) {
				foreach ($this->master->getFulfillCallbacks() as $func) {
					$func();
				}
			} else {
				foreach ($this->master->getRejectedCallbacks() as $func) {
					$func();
				}
			}
		}
	}

	public function start() : void {
		AsyncLoader::getInstance()->getScheduler()->scheduleRepeatingTask($this, 5);
	}
}
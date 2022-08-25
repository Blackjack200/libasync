<?php

namespace libasync\promise\task;

use libasync\AsyncLoader;
use libasync\promise\PromiseInterface;
use pocketmine\scheduler\Task;

class PromiseRaceTask extends Task {
	private PromiseInterface $master;
	/** @var PromiseInterface[] */
	public array $promises = [];
	private bool $settled = false;
	private bool|null $resolved = null;

	public function __construct(PromiseInterface $master) {
		$this->master = $master;
	}

	public function onRun() : void {
		if (!$this->settled) {
			/** @noinspection PsalmAdvanceCallableParamsInspection */
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
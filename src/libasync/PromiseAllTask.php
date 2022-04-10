<?php

namespace libasync;

use pocketmine\scheduler\Task;

class PromiseAllTask extends Task {
	private Promise $master;
	/** @var Promise[] */
	public array $promises = [];
	private bool $settled = false;
	private int $fulfilled = 0;
	private int $finished = 0;

	public function __construct(Promise $master) {
		$this->master = $master;
	}

	public function onRun() : void {
		if (!$this->settled) {
			$this->master->getAsyncCall()($this);
			foreach ($this->promises as $promise) {
				$promise->whenFulfill(function() : void {
					$this->fulfilled++;
					$this->finished++;
				})->whenReject(function() : void {
					$this->finished++;
				})->catch(function() : void {
					$this->finished++;
				})->settle();
			}
			$this->settled = true;
			return;
		}
		$cnt = count($this->promises);
		if ($this->finished === $cnt) {
			$this->getHandler()->cancel();
			if ($this->fulfilled === $cnt) {
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
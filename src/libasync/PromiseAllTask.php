<?php

namespace libasync;

use pocketmine\scheduler\Task;

class PromiseAllTask extends Task {
	private PromiseInterface $master;
	/** @var PromiseInterface[] */
	public array $promises = [];
	private bool $settled = false;
	private int $fulfilled = 0;
	private int $finished = 0;

	public function __construct(PromiseInterface $master) {
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
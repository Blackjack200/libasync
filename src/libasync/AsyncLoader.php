<?php

namespace libasync;

use libasync\await\EventLoop;
use libasync\global\GlobalRuntime;
use libasync\runtime\AsyncTaskRuntime;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;

class AsyncLoader extends PluginBase {
	private static self $instance;

	public static function getInstance() : self { return self::$instance; }

	protected function onLoad() : void {
		self::$instance = $this;

		if (!defined('bootstrap\PRODUCTION')) {
			define('bootstrap\PRODUCTION', false);
		}

		$lp = new EventLoop();
		GlobalRuntime::setRuntime(new AsyncTaskRuntime($this->getServer()->getAsyncPool()));
		GlobalRuntime::setLoop($lp);
		$this->getScheduler()->scheduleRepeatingTask(new ClosureTask(static fn() => $lp->poll(5)), 2);
	}
}
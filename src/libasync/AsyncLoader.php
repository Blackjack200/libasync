<?php

namespace libasync;

use libasync\await\EventLoop;
use libasync\global\GlobalAsyncRuntime;
use libasync\runtime\AsyncPoolRuntime;
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
		GlobalAsyncRuntime::setRuntime(new AsyncPoolRuntime($this->getServer()->getAsyncPool()));
		GlobalAsyncRuntime::setLoop($lp);
		$this->getScheduler()->scheduleRepeatingTask(new ClosureTask(static fn() => $lp->poll(10)), 1);
	}
}
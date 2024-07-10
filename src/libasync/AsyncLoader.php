<?php

namespace libasync;

use libasync\await\Await;
use libasync\await\ClassicEventLoop;
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

		GlobalAsyncRuntime::setRuntime(new AsyncPoolRuntime($this->getServer()->getAsyncPool()));

		//trigger autoloader to load Await class
		Await::class;

		$this->classLoop();
	}

	private function classLoop() : void {
		$lp = new ClassicEventLoop();
		GlobalAsyncRuntime::setLoop($lp);
		$this->getScheduler()->scheduleRepeatingTask(new ClosureTask(static fn() => $lp->poll(10)), 1);
	}
}
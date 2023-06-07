<?php

namespace libasync;

use libasync\await\Await;
use libasync\await\EventLoop;
use libasync\executor\Executor;
use libasync\executor\ThreadFactory;
use libasync\executor\ThreadPoolExecutor;
use libasync\global\GlobalRuntime;
use libasync\runtime\AsyncTaskRuntime;
use libasync\utils\LoggerUtils;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;

class AsyncLoader extends PluginBase {
	private static self $instance;

	public static function getInstance() : self { return self::$instance; }

	protected function onLoad() : void {
		self::$instance = $this;
		$lp = new EventLoop();
		GlobalRuntime::setRuntime(new AsyncTaskRuntime($this->getServer()->getAsyncPool()));
		GlobalRuntime::setLoop($lp);
		$this->getScheduler()->scheduleRepeatingTask(new ClosureTask(static fn() => $lp->poll()), 1);
	}
}
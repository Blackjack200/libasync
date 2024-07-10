<?php

namespace libasync;

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

		$autoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
		if (!file_exists($autoload)) {
			$this->getLogger()->critical("Composer autoloader not found at " . $autoload);
			$this->getServer()->getPluginManager()->disablePlugin($this);
			return;
		}
		require_once $autoload;
		require_once __DIR__ . '/functions.php';

		GlobalAsyncRuntime::setRuntime(new AsyncPoolRuntime($this->getServer()->getAsyncPool()));
		$lp = new ClassicEventLoop();
		GlobalAsyncRuntime::setLoop($lp);
		$this->getScheduler()->scheduleRepeatingTask(new ClosureTask(static fn() => $lp->poll(10)), 1);
	}
}
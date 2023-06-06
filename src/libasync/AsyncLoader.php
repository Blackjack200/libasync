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
		Await::sync( function() {
			$rt = new ThreadPoolExecutor(new ThreadFactory(Executor::class, LoggerUtils::makeLogger($this), '',
				static fn() => null,
				static fn() => null
			), 1);
			$rt->start();
			$x = yield from Await::async(static fn() => json_decode(file_get_contents('http://v.api.aa1.cn/api/yiyan/index.php?type=json'), true)['yiyan'],$rt);
			$x2 = yield from Await::async(static fn() => json_decode(file_get_contents('http://v.api.aa1.cn/api/api-wenan-anwei/index.php?type=json'), true)['anwei'],$rt);
			echo "今日神评: $x\n";
			echo "今日安慰: $x2\n";
			$rt->shutdown();
		});
	}
}
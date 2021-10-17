<?php

namespace libasync;

use pocketmine\plugin\PluginBase;

class AsyncLoader extends PluginBase {
	private static self $instance;

	public static function getInstance() : self {
		return self::$instance;
	}

	protected function onLoad() : void {
		self::$instance = $this;
	}
}
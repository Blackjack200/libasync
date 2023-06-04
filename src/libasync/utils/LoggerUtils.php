<?php

namespace libasync\utils;

use pocketmine\plugin\Plugin;
use pocketmine\Server;

final class LoggerUtils {
	private function __construct() {
	}

	public static function makeLogger(Plugin $plugin) : ThreadSafePluginLogger {
		$server = Server::getInstance();
		$prefix = $plugin->getDescription()->getPrefix();
		return new ThreadSafePluginLogger($server->getLogger(), $prefix !== '' ? $prefix : $plugin->getName());
	}
}
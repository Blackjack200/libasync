<?php

namespace libasync\executor;

use GlobalLogger;
use libasync\runtime\AsyncExecutionEnvironment;
use pocketmine\thread\log\ThreadSafeLogger;
use pocketmine\thread\Worker;

class ExecutorWorker extends Worker {
	private bool $readyToUse = false;

	public static array $paramsThreadLocal = [];

	public function __construct(
		private readonly ThreadSafeLogger           $logger,
		private readonly ?string                    $autoload,
		private readonly ?AsyncExecutionEnvironment $env,
	) {
	}

	protected function onRun() : void {
		GlobalLogger::set($this->logger);
		if ($this->autoload !== null) {
			require_once $this->autoload;
		}
		$this->readyToUse = true;
		static::$paramsThreadLocal[spl_object_id($this)] = $this->env->createArgs();
	}

	public function isReadyToUse() : bool { return $this->readyToUse; }

	protected function onShutdown() : void {
		$id = spl_object_id($this);
		if (isset(static::$paramsThreadLocal[$id])) {
			$this->env->destroyArgs(static::$paramsThreadLocal[$id]);
			unset(static::$paramsThreadLocal[$id]);
			GlobalLogger::get()->debug("Destroyed");
		}
		parent::onShutdown();
	}
}
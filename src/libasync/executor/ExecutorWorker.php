<?php

namespace libasync\executor;

use GlobalLogger;
use libasync\runtime\AsyncExecutionEnvironment;
use pmmp\thread\Runnable;
use pocketmine\snooze\SleeperHandlerEntry;
use pocketmine\snooze\SleeperNotifier;
use pocketmine\thread\log\ThreadSafeLogger;
use pocketmine\thread\ThreadManager;
use pocketmine\thread\Worker;
use pocketmine\utils\AssumptionFailedError;

class ExecutorWorker extends Worker {
	private bool $readyToUse = false;

	public static array $paramsThreadLocal = [];
	private SleeperHandlerEntry $sleeperEntry;
	private static ?SleeperNotifier $notifier = null;

	public static function getNotifier() : SleeperNotifier {
		if (static::$notifier !== null) {
			return static::$notifier;
		}
		throw new AssumptionFailedError("SleeperNotifier not found in thread-local storage");
	}

	public function __construct(
		private readonly ThreadSafeLogger           $logger,
		private readonly ?string                    $autoload,
		private readonly ?AsyncExecutionEnvironment $env,
	) {
	}

	public function setSleeperHandlerEntry(SleeperHandlerEntry $entry) : void {
		$this->sleeperEntry = $entry;
	}

	protected function onRun() : void {
		GlobalLogger::set($this->logger);
		if ($this->autoload !== null) {
			require_once $this->autoload;
		}
		$this->readyToUse = true;
		static::$paramsThreadLocal[spl_object_id($this)] = $this->env->createArgs();
		static::$notifier = $this->sleeperEntry->createNotifier();
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

	public function autoCollect() : void {
		$this->collect(static function(Runnable $runnable) : bool {
			if ($runnable instanceof ExecutorWorkerTask) {
				if ($runnable->isFinished()) {
					$runnable->onCompletion();
					return true;
				}
				return false;
			}
			return true;
		});
	}

	public function quit() : void {
		$this->isKilled = true;

		while ($this->getStacked() > 0 && !$this->isShutdown()) {
			$this->synchronized(function() : void {
				$this->autoCollect();
			});
		}
		$this->notify();
		$this->shutdown();

		ThreadManager::getInstance()->remove($this);
	}
}
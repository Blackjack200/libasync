<?php

namespace libasync\executor;

use libasync\runtime\AsyncExecutionEnvironment;
use libasync\utils\ThreadSafePrefixedLogger;
use pmmp\thread\Worker;
use pocketmine\Server;
use pocketmine\thread\log\ThreadSafeLogger;

readonly class WorkerFactory {
	public function __construct(
		private string                     $class,
		private ThreadSafeLogger           $logger,
		private string                     $autoload,
		private ?AsyncExecutionEnvironment $env,
	) {

	}

	public static function newCommon() : self {
		return new self(ExecutorWorker::class, Server::getInstance()->getLogger(), '',
			null,
		);
	}

	public function new(string $name) : Worker {
		return new ($this->class)(new ThreadSafePrefixedLogger($this->logger, "WorkerExecutor#$name"), $this->autoload, $this->env);
	}
}
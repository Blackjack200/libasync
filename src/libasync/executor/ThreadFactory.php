<?php

namespace libasync\executor;

use libasync\runtime\AsyncExecutionEnvironment;
use libasync\utils\ThreadSafePrefixedLogger;
use pocketmine\Server;
use pocketmine\thread\log\ThreadSafeLogger;

readonly class ThreadFactory {
	public function __construct(
		private string                     $class,
		private ThreadSafeLogger           $logger,
		private string                     $autoload,
		private ?AsyncExecutionEnvironment $env,
	) {

	}

	public static function newCommon() : self {
		return new self(Executor::class, Server::getInstance()->getLogger(), '',
			null,
		);
	}

	public function new(string $name) : Executor {
		return new ($this->class)(new ThreadSafePrefixedLogger($this->logger, "THREAD#$name"), $this->autoload, $this->env);
	}
}
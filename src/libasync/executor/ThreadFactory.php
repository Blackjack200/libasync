<?php

namespace libasync\executor;

use Closure;
use libasync\utils\ThreadSafePrefixedLogger;
use pmmp\thread\ThreadSafeArray;
use pocketmine\Server;
use pocketmine\thread\log\ThreadSafeLogger;
use Volatile;

class ThreadFactory {
	private Closure $defer;
	private Closure $prepareArgs;
	private string $autoload;
	private ThreadSafeLogger $logger;
	private string $class;

	public function __construct(
		string           $class,
		ThreadSafeLogger $logger,
		string           $autoload,
		Closure          $prepareArgs,
		Closure          $defer,
	) {
		$this->class = $class;
		$this->logger = $logger;
		$this->autoload = $autoload;
		$this->prepareArgs = $prepareArgs;
		$this->defer = $defer;
	}

	public static function newCommon() : self {
		$c = Server::getInstance()->getLoader();
		return new self(Executor::class, Server::getInstance()->getLogger(), '',
			static fn() => $c->register(true),
			static fn() => null
		);
	}

	public function setAutoload(string $autoload) : void { $this->autoload = $autoload; }

	public function setClass(string $class) : void { $this->class = $class; }

	public function setLogger(ThreadSafeLogger $logger) : void { $this->logger = $logger; }

	public function new(string $name) : Executor {
		return new ($this->class)(new ThreadSafePrefixedLogger($this->logger, "THREAD#$name"), $this->autoload, new ThreadSafeArray(), $this->prepareArgs, $this->defer);
	}
}
<?php

namespace libasync\executor;

use Closure;
use Logger;
use pocketmine\Server;
use PrefixedLogger;
use Volatile;

class ThreadFactory {
	private Closure $defer;
	private Closure $prepareArgs;
	private string $autoload;
	private Logger $logger;
	private string $class;

	public function __construct(
		string  $class,
		Logger  $logger,
		string  $autoload,
		Closure $prepareArgs,
		Closure $defer,
	) {
		$this->class = $class;
		$this->logger = $logger;
		$this->autoload = $autoload;
		$this->prepareArgs = $prepareArgs;
		$this->defer = $defer;
	}

	public function setAutoload(string $autoload) : void { $this->autoload = $autoload; }

	public function setClass(string $class) : void { $this->class = $class; }

	public function setLogger(Logger $logger) : void { $this->logger = $logger; }

	public function new(string $name) : Executor {
		return new ($this->class)(new PrefixedLogger($this->logger, "THREAD#$name"), $this->autoload, new Volatile(), $this->prepareArgs, $this->defer);
	}

	public static function newCommon() : self {
		$c = Server::getInstance()->getLoader();
		return new self(Executor::class, Server::getInstance()->getLogger(), '',
			static function (Executor $e) use ($c) : void {
				$c->register(true);
			},
			static function () : void {
			}
		);
	}
}
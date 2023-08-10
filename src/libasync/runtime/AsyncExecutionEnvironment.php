<?php

namespace libasync\runtime;

use Closure;
use libasync\utils\ClosureUtils;
use pmmp\thread\ThreadSafe;
use pocketmine\utils\Utils;
use Throwable;
use const bootstrap\PRODUCTION;

class AsyncExecutionEnvironment extends ThreadSafe {
	public function __construct(
		private readonly Closure $prepareArgs,
		private readonly Closure $defer,
	) {
		if (!PRODUCTION) {
			Utils::validateCallableSignature(static fn() : array => [], $this->prepareArgs);
			ClosureUtils::validateStatic($this->defer);
		}
	}

	public function prepareArgs() : array { return ($this->prepareArgs)(); }

	public function releaseArgs(array $args) : void { ($this->defer)(...$args); }

	public function run(Closure $f, array $injected = []) {
		$args = $this->prepareArgs();
		try {
			$result = $f(...$args, ...$injected);
			$this->releaseArgs($args);
			return $result;
		} catch (Throwable $thr) {
			$this->releaseArgs($args);
			throw $thr;
		}
	}

	public static function simple(Closure $new, Closure $free) : self {
		return new AsyncExecutionEnvironment(
			static fn() : array => [$new()],
			static fn($n) => $free($n),
		);
	}
}
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
		public readonly Closure $argsCtor,
		public readonly Closure $argsDtor,
	) {
		if (!PRODUCTION) {
			Utils::validateCallableSignature(static fn() : array => [], $this->argsCtor);
			ClosureUtils::validateStatic($this->argsDtor);
		}
	}

	public function createArgs() : array { return ($this->argsCtor)(); }

	public function destroyArgs(array $args) : void { ($this->argsDtor)(...$args); }

	public function run(Closure $f, array $injected = []) {
		$args = $this->createArgs();
		try {
			$result = $f(...$args, ...$injected);
			$this->destroyArgs($args);
			return $result;
		} catch (Throwable $thr) {
			$this->destroyArgs($args);
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
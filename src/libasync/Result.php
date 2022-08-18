<?php

namespace libasync;

use Closure;
use Throwable;

class Result {
	/**
	 * @param Closure(Closure $func):void $caller
	 * @param Closure(Closure $errorFunc):void $errorCaller
	 */
	public function __construct(
		private Closure $caller,
		private Closure $errorCaller,
	) {
	}

	/**
	 * @param Closure($this):void|null $c
	 */
	public function unwrap(?Closure $c = null) : void {
		if ($c === null) {
			$c = static fn() => null;
		}
		($this->caller)($c);
	}

	/**
	 * @param Closure(Closure $errorFunc):void $c
	 */
	public function catch(Closure $c) : self {
		($this->errorCaller)($c);
		return $this;
	}

	/**
	 * @param Result ...$results
	 */
	public function combine(...$results) : Result {
		if (count($results) < 1) {
			return $this;
		}
		array_unshift($results, $this);
		return new Result(static function(Closure $func) use ($results) : void {
			$args = [];
			$lastFunc = static function(...$a) use ($func, &$args) {
				$args = array_merge($args, $a);
				$func(...$args);
			};
			for ($i = count($results) - 1; $i > 0; $i--) {
				$result = $results[$i];
				$lastFunc = static function(...$a) use ($lastFunc, &$args, $result) {
					$args = array_merge($args, $a);
					$result->unwrap($lastFunc);
				};
			}
			$results[array_key_first($results)]->unwrap($lastFunc);
		}, static fn() => null);
	}

	public function promise() : PromiseInterface {
		return (new SyncedPromise())->bind(ResultPromiseCaller::class)->then(function($resolve, $reject, $errorHandler) : void {
			$this->unwrap(static function(...$args) use ($errorHandler, $resolve) : void {
				try {
					$resolve(...$args);
				} catch (Throwable $err) {
					$errorHandler($err);
				}
			});
		});
	}

	private static function empty() : Result {
		return new Result(static fn(Closure $c) => $c(), static fn() => null);
	}
}
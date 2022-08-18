<?php

namespace libasync\pool;

use Closure;
use libasync\Promise;

/**
 * @template T of scalar
 * @implements ResourcePoolInterface<T>
 */
class AsyncResourcePool implements ResourcePoolInterface {
	/** @var array<string, array{0:\Closure():(T|null), 1:\Closure(T):void} */
	private array $types = [];
	private array $resources = [];

	/**
	 * @inheritDoc
	 */
	public function register(string $type, Closure $prepareFunc, Closure $freeFunc) : void {
		$this->types[$type] = [$prepareFunc, $freeFunc];
		$this->resources[$type] = [];
		$this->prepare($type, 3);
	}

	private function prepare(string $type, int $count) : void {
		$prepareFunc = $this->types[$type][0];
		$promise = new Promise();
		$promise
			->then(static fn($resolve) => $resolve($prepareFunc()))
			/** @param T $val */
			->whenFulfill(fn(mixed $val) => $this->resources[$type][] = $val);
		for ($i = 0; $i < $count; $i++) {
			$promise->settle();
		}
	}

	public function isRegistered(string $type) : bool { return isset($this->types[$type]); }

	public function select(string $type) : ?ResourceRef {
		if (!isset($this->resources[$type])) {
			return null;
		}
		if (count($this->resources[$type]) === 0) {
			$this->prepare($type, 1);
			return null;
		}
		$res = array_pop($this->resources[$type]);
		$this->prepare($type, 1);
		return new ResourceRef($res, $this->types[$type][1]);
	}

	public function close() : void {
		foreach ($this->resources as $type => $rawRes) {
			$free = $this->types[$type][1];
			foreach ($rawRes as $res) {
				$free($res);
			}
		}
	}
}
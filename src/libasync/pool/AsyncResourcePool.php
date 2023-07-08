<?php

namespace libasync\pool;

use Closure;
use libasync\await\Await;
use libasync\utils\ResourceRef;

/**
 * @template T of scalar
 * @implements ResourcePoolInterface<T>
 */
class AsyncResourcePool implements ResourcePoolInterface {
	/** @var array<string, array{0:\Closure():(T|null), 1:\Closure(T):void, 2:Closure(T,Closure(T):void $push):void} */
	private array $types = [];
	private array $resources = [];
	private array $queued = [];

	/**
	 * @inheritDoc
	 */
	public function register(string $type, Closure $prepareFunc, Closure $freeFunc, Closure $recycleFunc) : void {
		$this->types[$type] = [$prepareFunc, $freeFunc, $recycleFunc];
		$this->resources[$type] = [];
		$this->queued[$type] = 0;
		$this->prepare($type, 5);
	}

	private function prepare(string $type, int $count) : void {
		$prepareFunc = $this->types[$type][0];
		$this->queued[$type] += $count;
		Await::sync(function() use ($count, $type, $prepareFunc) {
			for ($i = 0; $i < $count; $i++) {
				$rawRes = yield from $prepareFunc();
				$this->resources[$type][] = $rawRes;
				$this->queued[$type]--;
				yield from Await::sleep(1);
			}
		});
	}

	public function isRegistered(string $type) : bool { return isset($this->types[$type]); }

	public function select(string $type) : ?ResourceRef {
		if (!isset($this->resources[$type])) {
			return null;
		}
		$resourceCount = $this->queued[$type] + count($this->resources[$type]);
		if ($resourceCount === 0) {
			$this->prepare($type, 5);
			return null;
		}
		if (count($this->resources[$type]) === 0) {
			return null;
		}
		if ($resourceCount <= 5) {
			$this->prepare($type, 5 - $resourceCount);
		}
		$res = array_pop($this->resources[$type]);
		$userRecycle = $this->types[$type][2];
		$push = fn($res) => $this->resources[$type][] = $res;

		return new ResourceRef($res, $this->types[$type][1], static function($res) use ($push, $userRecycle) : bool {
			$pushed = false;
			$realPush = static function($res) use ($push, &$pushed) {
				$pushed = true;
				$push($res);
			};
			$userRecycle($res, $realPush);
			return $pushed;
		});
	}

	public function close() : void {
		foreach ($this->resources as $type => $rawRes) {
			$free = $this->types[$type][1];
			foreach ($rawRes as $res) {
				$free($res, true);
			}
		}
	}

	public function put(string $type, $resource) : void {
		if (!isset($this->resources[$type])) {
			throw new \InvalidArgumentException("Resource type $type is not registered");
		}
		$this->resources[$type][] = $resource;
	}
}
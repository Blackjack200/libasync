<?php

namespace libasync\pool;

use Closure;
use libasync\await\Await;
use libasync\await\AwaitResult;
use libasync\utils\ResourceRef;

/**
 * @template T of scalar
 * @implements ResourcePoolInterface<T>
 */
class AsyncResourcePool implements ResourcePoolInterface {
	protected int $standardCapacity;
	/** @var array<string, array{0:\Closure():(T|null), 1:\Closure(T):void, 2:Closure(T,Closure(T):void $push):void} */
	private array $types = [];
	private array $resourcesHandle = [];
	private array $queued = [];

	/**
	 * @inheritDoc
	 */
	public function register(string $type, Closure $prepareFunc, Closure $freeFunc, Closure $recycleFunc) : void {
		$this->types[$type] = [$prepareFunc, $freeFunc, $recycleFunc];
		$this->resourcesHandle[$type] = [];
		$this->queued[$type] = 0;
		$this->standardCapacity = 2;
	}

	public function prepare(string $type, int $count) : AwaitResult {
		$prepareFunc = $this->types[$type][0];
		$this->queued[$type] += $count;
		return Await::do(function() use ($count, $type, $prepareFunc) {
			for ($i = 0; $i < $count; $i++) {
				$rawRes = $prepareFunc();
				$this->resourcesHandle[$type][] = $rawRes;
				$this->queued[$type]--;
				Await::sleep(1);
			}
		});
	}

	public function isRegistered(string $type) : bool { return isset($this->types[$type]); }

	public function select(string $type) : ?ResourceRef {
		if (!isset($this->resourcesHandle[$type])) {
			return null;
		}
		$resourceCount = $this->queued[$type] + count($this->resourcesHandle[$type]);
		if ($resourceCount === 0) {
			$this->prepare($type, $this->standardCapacity)->panic();
			return null;
		}
		if (count($this->resourcesHandle[$type]) === 0) {
			return null;
		}
		$res = array_pop($this->resourcesHandle[$type]);
		$userRecycle = $this->types[$type][2];
		$push = fn($res) => $this->resourcesHandle[$type][] = $res;

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
		foreach ($this->resourcesHandle as $type => $rawRes) {
			$free = $this->types[$type][1];
			foreach ($rawRes as $res) {
				$free($res, true);
			}
		}
	}

	public function put(string $type, $resource) : void {
		if (!isset($this->resourcesHandle[$type])) {
			throw new \InvalidArgumentException("Resource type $type is not registered");
		}
		$this->resourcesHandle[$type][] = $resource;
	}
}
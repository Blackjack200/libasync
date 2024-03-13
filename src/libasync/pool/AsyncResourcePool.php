<?php

namespace libasync\pool;

use Closure;
use Generator;
use libasync\await\Await;
use libasync\await\AwaitResult;
use libasync\utils\ResourceRef;

/**
 * @template T
 * @implements ResourcePoolInterface<T>
 */
class AsyncResourcePool implements ResourcePoolInterface {
	public function __construct(
		/** @var Closure():T */
		private readonly Closure $prepareFunc,
		/** @var Closure(T $res,bool $force):void */
		private readonly Closure $freeFunc,
		/** @var Closure(T $res,Closure $push):bool */
		private readonly Closure $recycleFunc,
	) {

	}

	protected int $capacity;
	/** @var T[] */
	private array $resources = [];
	private int $queued = 0;

	public function setCapacity(int $capacity) : void { $this->capacity = $capacity; }

	public function prepare(int $count) : AwaitResult {
		$this->queued += $count;
		return Await::do(function() use ($count) {
			for ($i = 0; $i < $count; $i++) {
				if (count($this->resources) + 1 > $this->capacity) {
					break;
				}
				$g = ($this->prepareFunc)();
				if ($g instanceof Generator) {
					$g = yield from $g;
				}
				$this->resources[] = $g;
				$this->queued--;
				yield;
			}
		});
	}

	public function select() : ?ResourceRef {
		$resourceCount = $this->queued + count($this->resources);
		if ($resourceCount === 0) {
			$this->prepare($this->capacity)->logError();
			return null;
		}
		if (count($this->resources) === 0) {
			return null;
		}
		$res = array_pop($this->resources);
		return $this->packRes($res);
	}

	public function close() : void {
		foreach ($this->resources as $res) {
			($this->freeFunc)($res, true);
		}
	}

	/**
	 * @param T $res
	 */
	private function packRes($res) : ResourceRef {
		return new ResourceRef(
			res: $res,
			recyclable: true,
			freeFunc: $this->freeFunc,
			recycleFunc: function($res) : bool {
				$recycled = false;
				$realPush = function($res) use (&$recycled) {
					$recycled = true;
					$this->resources[] = $res;
				};
				($this->recycleFunc)($res, $realPush);
				return $recycled;
			});
	}
}
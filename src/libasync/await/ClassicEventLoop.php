<?php

namespace libasync\await;

use Closure;

class ClassicEventLoop implements EventLoop {
	/** @var array<int, Closure(\Closure $unregister):void> */
	private array $poll = [];
	/** @var array<int, Closure(\Closure $unregister):void> */
	private array $slept = [];
	/** @var int[] */
	private array $awake = [];

	public function poll(int $microsecond = PHP_INT_MAX) : void {
		$pending = [];
		foreach ($this->poll as $k => $await) {
			$break = function() use ($k) : void { unset($this->poll[$k]); };
			$changeToWakeup = function() use ($k) : Closure {
				$this->slept[$k] = $this->poll[$k];
				unset($this->poll[$k]);
				return function() use ($k) {
					$this->awake[$k] = 1;
				};
			};

			$pending[] = static fn() => $await($break, $changeToWakeup);
		}

		foreach ($this->awake as $k => $_) {
			$this->poll[$k] = $this->slept[$k];
			unset($this->slept[$k]);
		}
		$this->awake = [];

		$d = $microsecond * 1000 * 1000;
		$start = hrtime(true);
		foreach ($pending as $await) {
			$now = hrtime(true) - $start;
			if ($now >= $d) {
				break;
			}
			$await();
		}
	}

	/**
	 * @param Closure(Closure $break):void $c
	 */
	public function add(Closure $c) : Closure {
		$id = spl_object_id($c);
		$this->poll[$id] = $c;
		return function() use ($id) {
			unset($this->poll[$id], $this->slept[$id]);
		};
	}
}
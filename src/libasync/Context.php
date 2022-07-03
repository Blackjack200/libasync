<?php

namespace libasync;

use Closure;
use Throwable;

class Context {
	/** @var Closure[] */
	private array $after = [];
	/** @var Closure[] */
	private array $catch = [];

	public static function new() : self {
		return new self();
	}

	public function do(Closure $cal) : void {
		/*$args = [];
		$changeArgs = static function(...$a) use (&$args) : void { $args = $a; };*/
		$execute = static function(array $cal, array $args, bool $throw) : void {
			foreach ($cal as $f) {
				$f(...$args);
			}
			if($throw) {
				throw new InterruptSignal();
			}
		};
		$done = fn(...$args) => $execute($this->after, $args, true);
		$catch = fn(Throwable $e) => $execute($this->catch, [$e], false);
		try {
			$cal($done);
		} catch (Throwable $e) {
			if (!($e instanceof InterruptSignal)) {
				$catch($e);
			}
		}
	}


	public function after(Closure $cal) : self {
		$this->after[] = $cal;
		return $this;
	}

	public function catch(Closure $cal) : self {
		$this->catch[] = $cal;
		return $this;
	}
}
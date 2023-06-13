<?php

namespace libasync\runtime;

use Closure;
use libasync\exception\AsyncExecutionException;
use pmmp\thread\ThreadSafeArray;
use pocketmine\scheduler\AsyncTask;
use pocketmine\utils\Utils;
use Throwable;

class AsyncExecutionTask extends AsyncTask {

	public function __construct(
		private readonly AsyncExecutionReceipt $rec,
		private readonly Closure               $closure,
		/** @var null|Closure(AsyncExecutionReceipt):array */
		private readonly ?Closure              $extraArgPrepareFunc,
		/** @var null|Closure(...$args):void */
		private readonly ?Closure              $extraArgDestroyFunc,
	) {
	}

	public function onRun() : void {
		try {
			if ($this->extraArgPrepareFunc !== null) {
				$args = ($this->extraArgPrepareFunc)($this->reci);
			} else {
				$args = [];
			}
			try {
				$result = ($this->closure)(...$args);
				$this->reci->setResult($result);
			} catch (Throwable $err) {
				$this->setError($err);
			}
			if ($this->extraArgDestroyFunc !== null) {
				($this->extraArgDestroyFunc)(...$args);
			}
		} catch (Throwable $err) {
			$this->setError($err);
		}
	}

	private function setError(Throwable $err) : void {
		$this->reci->setError(AsyncExecutionException::wrap($err));
	}
}
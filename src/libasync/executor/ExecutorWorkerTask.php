<?php

namespace libasync\executor;

use libasync\exception\ExecutionExceptionWrapper;
use libasync\runtime\AsyncExecutionEnvironment;
use libasync\runtime\AsyncExecutionReceipt;
use libasync\utils\Utils;
use pmmp\thread\Runnable;
use pmmp\thread\Thread;
use pocketmine\utils\AssumptionFailedError;
use Throwable;

class ExecutorWorkerTask extends Runnable {
	/** @var AsyncExecutionReceipt[] */
	private static array $threadLocalReceipt = [];
	private ?string $error = null;
	private mixed $result;
	private bool $finished = false;

	public function __construct(
		AsyncExecutionReceipt                       $receipt,
		private readonly \Closure                   $closure,
		private readonly ?AsyncExecutionEnvironment $env = null
	) {
		static::$threadLocalReceipt[spl_object_id($this)] = $receipt;
	}

	private function setError(Throwable $err) : void { $this->error = igbinary_serialize(ExecutionExceptionWrapper::wrap($err)); }

	public function isFinished() : bool { return $this->finished; }

	public function run() : void {
		$thread = Thread::getCurrentThread();
		if (!($thread instanceof ExecutorWorker)) {
			throw new AssumptionFailedError("This should never happens.");
		}
		try {
			$params = ExecutorWorker::$paramsThreadLocal[spl_object_id($thread)];
			try {
				if ($this->env !== null) {
					$result = $this->env->run($this->closure, $params);
				} else {
					$result = ($this->closure)(...$params);
				}
				$this->result = Utils::smartSerialize($result);
			} catch (Throwable $err) {
				$this->setError($err);
			}
		} catch (Throwable $err) {
			$this->setError($err);
		} finally {
			$this->finished = true;
			ExecutorWorker::getNotifier()->wakeupSleeper();
		}
		gc_collect_cycles();
	}

	public function onCompletion() : void {
		$receipt = static::$threadLocalReceipt[spl_object_id($this)];
		unset(static::$threadLocalReceipt[spl_object_id($this)]);
		if ($this->error !== null) {
			$receipt->setError(igbinary_unserialize($this->error));
		} else {
			$receipt->setResult(Utils::smartDeserialize($this->result));
		}
	}
}
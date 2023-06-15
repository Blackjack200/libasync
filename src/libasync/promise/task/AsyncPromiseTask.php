<?php

namespace libasync\promise\task;

use libasync\await\Await;
use libasync\exception\AsyncExecutionException;
use libasync\InterruptSignal;
use libasync\promise\Promise;
use libasync\promise\PromiseInterface;
use libasync\runtime\AsyncExecutionReceipt;
use libasync\runtime\AsyncRuntime;
use libasync\utils\Utils;
use pmmp\thread\ThreadSafeArray;
use Throwable;

readonly class AsyncPromiseTask {
	public function __construct(private Promise $promise) { }

	final public function start() : void {
		$promise = $this->promise;
		self::awaitRun($promise);
	}

	public static function awaitRun(PromiseInterface $promise, ?AsyncRuntime $runtime = null) : void {
		Await::sync(static function() use ($runtime, $promise) {
			try {
				$call = $promise->getAsyncCall();
				[$rejected, $result, $callTrace] = Utils::smartDeserialize(yield from self::promiseAsyncRun($call, $runtime));
				$callTrace = Utils::smartDeserialize($callTrace);
				$result = Utils::smartDeserialize($result);
				try {
					$c = $rejected ? $promise->getRejectedCallbacks() : $promise->getFulfillCallbacks();
					foreach ($c as $cl) {
						$cl(...$result);
					}
				} catch (Throwable $err) {
					AsyncExecutionException::wrap($err)->printWithCallTrace(\GlobalLogger::get(), $callTrace);
				}
			} catch (Throwable $err) {
				if ($promise->getErrorHandler() !== null) {
					$promise->getErrorHandler()(AsyncExecutionException::wrap($err));
				} else {
					throw $err;
				}
			}
		});
	}

	private static function promiseAsyncRun(\Closure $call, ?AsyncRuntime $runtime) : \Generator {
		return Await::async(
			static function(...$args) use ($call) {
				$i = $args[2]();
				assert($i instanceof AsyncExecutionReceipt);
				unset($args[2]);
				$arr = [];
				foreach ($args as $arg) {
					$arr[] = $arg;
				}
				try {
					$ret = $call(...$arr);
					if (!$i->isFinished()) {
						$i->setResult(ThreadSafeArray::fromArray([false, Utils::smartSerialize([$ret]), Utils::smartSerialize($i->getCallTrace())]));
					}
				} catch (Throwable $err) {
					if (!($err instanceof InterruptSignal)) {
						throw $err;
					}
				}
				return Utils::smartSerialize($i->getResult());
			},
			$runtime,
			static fn(AsyncExecutionReceipt $i) => [
				static function(...$res) use ($i) : void {
					$i->setResult(ThreadSafeArray::fromArray([false, Utils::smartSerialize($res), Utils::smartSerialize($i->getCallTrace())]));
					throw new InterruptSignal();
				},
				static function(...$reason) use ($i) : void {
					$i->setResult(ThreadSafeArray::fromArray([true, Utils::smartSerialize($reason), Utils::smartSerialize($i->getCallTrace())]));
					throw new InterruptSignal();
				},
				static fn() => $i,
			],
		);
	}
}
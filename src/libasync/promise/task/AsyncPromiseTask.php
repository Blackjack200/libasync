<?php

namespace libasync\promise\task;

use libasync\await\Await;
use libasync\exception\AsyncExecutionException;
use libasync\InterruptSignal;
use libasync\promise\Promise;
use libasync\promise\PromiseInterface;
use libasync\runtime\AsyncExecutionRecipient;
use libasync\runtime\AsyncRuntime;
use libasync\utils\Utils;
use pmmp\thread\ThreadSafeArray;
use Throwable;

readonly class AsyncPromiseTask {
	public function __construct(private Promise $promise) { }

	final public function start() : void {
		$promise = $this->promise;
		$this->awaitRun($promise);
	}

	public static function awaitRun(PromiseInterface $promise, ?AsyncRuntime $runtime = null) : void {
		Await::sync(static function() use ($runtime, $promise) {
			try {
				$call = $promise->getAsyncCall();
				$ret = yield from Await::async(
					static function(...$args) use ($call) {
						$i = $args[2]();
						assert($i instanceof AsyncExecutionRecipient);
						unset($args[2]);
						$arr = [];
						foreach ($args as $arg) {
							$arr[] = $arg;
						}
						try {
							$ret = $call(...$arr);
							if (!$i->isFinished()) {
								$i->setResult([false, Utils::smartSerialize($ret)]);
							}
						} catch (Throwable $err) {
							if (!($err instanceof InterruptSignal)) {
								throw $err;
							}
						}
						return igbinary_serialize($i->getResult());
					},
					$runtime,
					static fn(AsyncExecutionRecipient $i) => [
						static function(...$res) use ($i) : void {
							$i->setResult([false, Utils::smartSerialize($res)]);
							throw new InterruptSignal();
						},
						static function(...$reason) use ($i) : void {
							$i->setResult([true, Utils::smartSerialize($reason)]);
							throw new InterruptSignal();
						},
						static fn() => $i,
					],
				);
				[$rejected, $result] = igbinary_unserialize($ret);
				$c = $rejected ? $promise->getRejectedCallbacks() : $promise->getFulfillCallbacks();
				$ff = Utils::smartDeserialize($result);
				foreach ($c as $cl) {
					$cl(...$ff);
				}
			} catch (Throwable $err) {
				if ($promise->getErrorHandler() !== null) {
					$promise->getErrorHandler()(AsyncExecutionException::from(ThreadSafeArray::fromArray([$err::class, $err->getMessage(), igbinary_serialize(\pocketmine\utils\Utils::printableTrace($err->getTrace())), $err->getCode(), $err->getFile(), $err->getLine()])));
				} else {
					throw $err;
				}
			}
		});
	}
}
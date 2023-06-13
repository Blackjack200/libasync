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
				$ret = yield from Await::async(
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
								$i->setResult([false, Utils::smartSerialize($ret), $i->getCallTrace()]);
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
							$i->setResult([false, Utils::smartSerialize($res), $i->getCallTrace()]);
							throw new InterruptSignal();
						},
						static function(...$reason) use ($i) : void {
							$i->setResult([true, Utils::smartSerialize($reason), $i->getCallTrace()]);
							throw new InterruptSignal();
						},
						static fn() => $i,
					],
				);
				try {
					[$rejected, $result, $callTrace] = Utils::smartDeserialize($ret);
					$c = $rejected ? $promise->getRejectedCallbacks() : $promise->getFulfillCallbacks();
					$ff = Utils::smartDeserialize($result);
					foreach ($c as $cl) {
						$cl(...$ff);
					}
				} catch (Throwable $thr) {
					var_dump($ret);
					\GlobalLogger::get()->critical(
						"\n--- Call Stack trace ---\n" .
						implode("\n", igbinary_unserialize($callTrace ?? igbinary_serialize([]))) .
						"\n--- End of exception information ---"
					);
					throw $thr;
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
}
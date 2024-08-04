<?php

namespace libasync {

	use Closure;
	use Generator;
	use libasync\await\Await;
	use libasync\await\AwaitResult;
	use libasync\await\AwaitSignal;
	use libasync\await\Coroutine;
	use libasync\await\EventLoop;
	use libasync\exception\ExecutionException;
	use libasync\runtime\AsyncExecutionEnvironment;
	use libasync\runtime\AsyncRuntime;
	use pocketmine\player\Player;

	function async(Closure|Generator $block, ?EventLoop $loop = null) : AwaitResult {
		return Await::do($block, $loop);
	}

	/**
	 * @template T
	 * @param Closure():T $do
	 * @return Generator<void,AwaitSignal|mixed,void,T>|T
	 * @throws ExecutionException
	 */
	function thread(Closure $do, ?AsyncRuntime $runtime = null, ?AsyncExecutionEnvironment $env = null) {
		return Await::threadify($do, $runtime, $env);
	}

	function trap(Closure $c) : void {
		if (Coroutine::$RUNNING === null) {
			throw new \RuntimeException("no coroutine running in this context");
		}
		Coroutine::$RUNNING->addTrap($c);
	}

	function may_drop() : void {
		if (Coroutine::$RUNNING === null) {
			throw new \RuntimeException("no coroutine running in this context");
		}
		unset(Coroutine::$joinedCoroutine[spl_object_id(Coroutine::$RUNNING)]);
	}

	function trap_online(Player $player) : void {
		trap(static fn() => $player->isOnline());
	}

	function timeout(float $second) : void {
		if (Coroutine::$RUNNING === null) {
			throw new \RuntimeException("no coroutine running in this context");
		}
		Coroutine::$RUNNING->timeout = $second;
	}
}
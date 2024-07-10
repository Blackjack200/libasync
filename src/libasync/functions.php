<?php

namespace libasync {

	use Closure;
	use Generator;
	use libasync\await\Await;
	use libasync\await\AwaitResult;
	use libasync\await\AwaitSignal;
	use libasync\await\EventLoop;
	use libasync\exception\ExecutionException;
	use libasync\runtime\AsyncExecutionEnvironment;
	use libasync\runtime\AsyncRuntime;

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
}
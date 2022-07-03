<?php

namespace libasync;

use Closure;
use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;
use pocketmine\utils\AssumptionFailedError;

class AsyncPromiseTask extends AsyncTask {
	protected Closure $cal;
	use PromiseRuntime;

	//protected string $backtrace;

	public function __construct(PromiseInterface $promise) {
		$this->cal = $promise->getAsyncCall();
		$this->storeLocal('promise', $promise);
		/*ob_start();
		debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
		$this->backtrace = ob_get_clean();*/
	}

	final public function onRun() : void {
		$this->runFunc($this->cal);
	}

	final public function start() : void {
		Server::getInstance()->getAsyncPool()->submitTask($this);
	}

	public function onCompletion() : void {
		$promise = $this->fetchLocal('promise');
		if (!$promise instanceof PromiseInterface) {
			throw new AssumptionFailedError('ThreadLocal should return Promise back.');
		}
		$this->onFinished($promise);
	}
}
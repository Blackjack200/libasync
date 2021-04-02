<?php


namespace libasync;


use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;
use Threaded;

class PromiseAsyncTask extends AsyncTask {
	/** @var Threaded<callable() : bool> */
	private Threaded $cal;
	/** @var mixed */
	private $ret;
	
	public function __construct(Promise $promise) {
		$this->cal = $promise->getAsync();
		$this->storeLocal([$promise]);
	}
	
	public function onRun() : void {
		foreach ($this->cal as $value) {
			$this->ret = $value();
			if ($this->ret === true) {
				break;
			}
		}
	}
	
	public function onCompletion(Server $server) : void {
		/** @var Promise $promise */
		[$promise] = $this->fetchLocal();
		foreach ($promise->getResultConsumer() as $consumer) {
			if ($consumer($this->ret)) {
				break;
			}
		}
	}
	
	public function start() : void {
		Server::getInstance()->getAsyncPool()->submitTask($this);
	}
}
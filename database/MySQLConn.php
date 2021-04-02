<?php


namespace libasync\database;


use libasync\Promise;
use libasync\PromiseAsyncTask;
use pocketmine\Server;
use Threaded;

class MySQLConn extends PromiseAsyncTask {
	private ConnInfo $info;
	/** @var Threaded<callable() : bool> */
	private Threaded $cal;
	/** @var mixed */
	private $ret;
	
	/**
	 * @noinspection MagicMethodsValidityInspection
	 * @noinspection PhpMissingParentConstructorInspection
	 */
	public function __construct(Promise $promise, ConnInfo $info) {
		$this->cal = $promise->getAsync();
		$this->info = $info;
		$this->storeLocal([$promise]);
	}
	
	public function onRun() : void {
		$conn = mysqli_connect(
			$this->info->getHost(),
			$this->info->getUserName(),
			$this->info->getPassword(),
			$this->info->getDataBase(),
			$this->info->getPort()
		);
		foreach ($this->cal as $value) {
			$this->ret = $value($conn);
			if ($this->ret === true) {
				break;
			}
		}
		$conn->close();
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
}
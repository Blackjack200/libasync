<?php


namespace libasync\database;


use libasync\IPromise;
use libasync\PromiseAsyncTask;
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
	public function __construct(IPromise $promise, ConnInfo $info) {
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
}
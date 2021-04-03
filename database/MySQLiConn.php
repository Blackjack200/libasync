<?php


namespace libasync\database;


use libasync\IPromise;
use libasync\PromiseAsyncTask;
use mysqli_result;

class MySQLiConn extends PromiseAsyncTask {
	private MySQLiConnInfo $info;

	public function __construct(IPromise $promise, MySQLiConnInfo $info) {
		parent::__construct($promise);
		$this->info = $info;
	}

	public function onRun() : void {
		$conn = mysqli_connect(
			$this->info->host,
			$this->info->username,
			$this->info->password,
			$this->info->dataBase,
			$this->info->port
		);
		foreach ($this->cal as $value) {
			$this->ret = $this->serializeData($value($conn));
			if ($this->ret === self::EXECUTE_DROP) {
				break;
			}
		}
		$conn->close();
	}

	public function serializeData($val) : string {
		if ($val instanceof mysqli_result) {
			$val = $val->fetch_assoc();
		}
		return parent::serializeData($val);
	}
}
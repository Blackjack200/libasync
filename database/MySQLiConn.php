<?php

namespace libasync\database;

use libasync\PromiseInterface;
use libasync\PromiseAsyncTask;
use mysqli;
use mysqli_result;
use function mysqli_connect;
use function usleep;

class MySQLiConn extends PromiseAsyncTask {
	private MySQLiConnInfo $info;

	public function __construct(PromiseInterface $promise, MySQLiConnInfo $info) {
		parent::__construct($promise);
		$this->info = $info;
	}

	public function onRun() : void {
		$i = 0;
		$conn = mysqli_connect(
			$this->info->host,
			$this->info->username,
			$this->info->password,
			$this->info->database,
			$this->info->port
		);
		while ($i++ < $this->info->retry && !$conn instanceof mysqli) {
			$conn = mysqli_connect(
				$this->info->host,
				$this->info->username,
				$this->info->password,
				$this->info->database,
				$this->info->port
			);
			usleep(20);
		}
		while ($this->cal->count() > 0) {
			$value = $this->cal->shift();
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
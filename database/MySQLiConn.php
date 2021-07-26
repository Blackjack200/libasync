<?php

namespace libasync\database;

use libasync\ArgInfo;
use libasync\PromiseAsyncTask;
use libasync\PromiseInterface;
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

	protected function getExtraArgs() : array {
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
		return [new ArgInfo($conn, static function () use ($conn) : void {
			mysqli_close($conn);
		})];
	}

	protected function serializeData($val) : string {
		if ($val instanceof mysqli_result) {
			$val = $val->fetch_assoc();
		}
		return parent::serializeData($val);
	}
}
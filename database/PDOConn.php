<?php

namespace libasync\database;

use libasync\PromiseAsyncTask;
use libasync\PromiseInterface;
use PDO;

class PDOConn extends PromiseAsyncTask {
	protected PDOConnInfo $info;

	public function __construct(PromiseInterface $promise, PDOConnInfo $info) {
		parent::__construct($promise);
		$this->info = $info;
	}

	protected function getExtraArgs() : array {
		return [new PDO($this->info->dsn,
			$this->info->username,
			$this->info->password,
			$this->info->options
		)];
	}
}
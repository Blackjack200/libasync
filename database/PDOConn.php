<?php

namespace libasync\database;

use libasync\PromiseInterface;
use libasync\PromiseAsyncTask;
use PDO;

class PDOConn extends PromiseAsyncTask {
	protected PDOConnInfo $info;

	public function __construct(PromiseInterface $promise, PDOConnInfo $info) {
		parent::__construct($promise);
		$this->info = $info;
	}

	public function onRun() : void {
		$pdo = new PDO($this->info->dsn, $this->info->username, $this->info->password, $this->info->options);
		foreach ($this->cal as $value) {
			$this->ret = $this->serializeData($value($pdo));
			if ($this->ret === self::EXECUTE_DROP) {
				break;
			}
		}
	}
}
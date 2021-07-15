<?php

namespace libasync\database;

use libasync\PromiseInterface;

class DataBase {
	public static function mysqli(PromiseInterface $promise, MySQLiConnInfo $info) : void {
		$task = new MySQLiConn($promise, $info);
		$task->start();
	}

	public static function pdo(PromiseInterface $promise, PDOConnInfo $info) : void {
		$task = new PDOConn($promise, $info);
		$task->start();
	}
}
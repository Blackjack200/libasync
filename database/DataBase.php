<?php


namespace libasync\database;


use libasync\IPromise;

class DataBase {
	public static function mysqli(IPromise $promise, MySQLiConnInfo $info) : void {
		$task = new MySQLiConn($promise, $info);
		$task->start();
	}

	public static function pdo(IPromise $promise, PDOConnInfo $info) : void {
		$task = new PDOConn($promise, $info);
		$task->start();
	}
}
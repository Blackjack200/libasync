<?php


namespace libasync\database;


use libasync\IPromise;

class MySQL {
	public static function start(IPromise $promise, ConnInfo $info) : void {
		$task = new MySQLConn($promise, $info);
		$task->start();
	}
}
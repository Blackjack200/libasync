<?php


namespace libasync\database;


use libasync\Promise;

class MySQL {
	public static function start(Promise $promise, ConnInfo $info) : void {
		$task = new MySQLConn($promise, $info);
		$task->start();
	}
}
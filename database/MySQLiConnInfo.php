<?php

namespace libasync\database;

use Threaded;

class MySQLiConnInfo extends Threaded {
	public string $host;
	public ?string $username = null;
	public ?string $password = null;
	public ?string $dataBase = null;
	public int $port;
	public ?int $retry = null;
}
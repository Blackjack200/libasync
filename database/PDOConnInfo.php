<?php

namespace libasync\database;

use Threaded;

class PDOConnInfo extends Threaded {
	public string $dsn;
	public ?string $username = null, $password = null;
	public ?array $options = null;
}
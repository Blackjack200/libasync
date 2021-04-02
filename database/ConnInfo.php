<?php


namespace libasync\database;


use Threaded;


class ConnInfo extends Threaded {
	protected ?string $host;
	protected ?string $userName;
	protected ?string $password;
	protected ?string $dataBase;
	protected ?int $port;
	protected ?int $retry;
	
	public function getHost() : ?string {
		return $this->host;
	}
	
	/** @phpstan-return self<mixed> */
	public function setHost(?string $host) : self {
		$this->host = $host;
		return $this;
	}
	
	public function getUserName() : ?string {
		return $this->userName;
	}
	
	/** @phpstan-return self<mixed> */
	public function setUserName(?string $userName) : self {
		$this->userName = $userName;
		return $this;
	}
	
	public function getPassword() : ?string {
		return $this->password;
	}
	
	/** @phpstan-return self<mixed> */
	public function setPassword(?string $password) : self {
		$this->password = $password;
		return $this;
	}
	
	public function getDataBase() : ?string {
		return $this->dataBase;
	}
	
	/** @phpstan-return self<mixed> */
	public function setDataBase(?string $dataBase) : self {
		$this->dataBase = $dataBase;
		return $this;
	}
	
	public function getPort() : ?int {
		return $this->port;
	}
	
	/** @phpstan-return self<mixed> */
	public function setPort(?int $port) : self {
		$this->port = $port;
		return $this;
	}
	
	public function getRetry() : ?int {
		return $this->retry;
	}
	
	/** @phpstan-return self<mixed> */
	public function setRetry(?int $retry) : self {
		$this->retry = $retry;
		return $this;
	}
}
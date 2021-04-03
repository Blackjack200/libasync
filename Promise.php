<?php


namespace libasync;


use Threaded;

class Promise implements IPromise {
	/** @var Threaded<callable> */
	protected Threaded $async;
	/** @var callable[] */
	protected array $res = [];

	public function __construct() {
		$this->async = new Threaded();
	}

	public function then(callable $cal) : self {
		$this->async[] = $cal;
		return $this;
	}

	public function whenResult(callable $cal) : self {
		$this->res[] = $cal;
		return $this;
	}

	/**
	 * @return Threaded<callable>
	 */
	public function getAsyncConsumer() : Threaded {
		return $this->async;
	}

	public function getResultConsumer() : array {
		return $this->res;
	}
}
<?php


namespace libasync;


use Threaded;

class PromiseBase implements Promise {
	/** @var Threaded<callable> */
	protected Threaded $async;
	/** @var callable[] */
	protected array $res = [];
	
	public function with(callable $cal) : self {
		$this->async[] = $cal;
		return $this;
	}
	
	public function whenResult(callable $cal) : self {
		$this->res[] = $cal;
		return $this;
	}
	
	/**
	 * @return Threaded<callable() : bool>
	 */
	public function getAsync() : Threaded {
		return $this->async;
	}
	
	public function getResultConsumer() : array {
		return $this->res;
	}
}
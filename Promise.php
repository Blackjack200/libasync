<?php


namespace libasync;


use RuntimeException;
use Threaded;

class Promise implements IPromise {
	/** @var Threaded<callable> */
	protected Threaded $async;
	/** @var callable[] */
	protected array $res = [];
	/** @var callable */
	protected $rejectConsumer;
	protected array $rejectContext = [];
	protected bool $rejected = false;

	public function __construct() {
		$this->async = new Threaded();
	}

	public function then(callable $cal) : self {
		$this->async->synchronized(function () use ($cal) {
			$this->async[] = $cal;
		});
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

	public function isRejected() : bool {
		return $this->rejected;
	}

	public function whenReject(callable $cal) : IPromise {
		$this->rejectConsumer = $cal;
		return $this;
	}

	public function reject(...$context) : void {
		if ($this->rejected) {
			throw new RuntimeException('Reject rejected Promise');
		}
		$this->rejectContext = $context;
		$this->rejected = true;
	}

	public function getRejectConsumer() : callable {
		return $this->rejectConsumer;
	}

	public function getRejectContext() : array {
		return $this->rejectContext;
	}
}
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
	protected array $rejectReason = [];
	protected bool $rejected = false;
	protected string $class = PromiseAsyncTask::class;

	public function __construct() {
		$this->async = new Threaded();
	}

	public function bind(string $class) : Promise {
		$this->class = $class;
		return $this;
	}

	public function then(callable $cal) : self {
		$this->async[] = $cal;
		return $this;
	}

	public function whenResult(callable $cal) : self {
		$this->res[] = $cal;
		return $this;
	}

	public function whenReject(callable $cal) : IPromise {
		$this->rejectConsumer = $cal;
		return $this;
	}

	public function reject(...$reason) : void {
		if ($this->rejected) {
			throw new RuntimeException('Reject rejected Promise');
		}
		$this->rejectReason = $reason;
		$this->rejected = true;
	}

	public function start(...$args) : void {
		Promises::start($this, $this->class, $args);
	}

	public function getRejectConsumer() : callable {
		return $this->rejectConsumer;
	}

	public function getRejectReason() : array {
		return $this->rejectReason;
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
}
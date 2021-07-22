<?php

namespace libasync;

use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;
use Threaded;
use function igbinary_serialize;
use function igbinary_unserialize;

class PromiseAsyncTask extends AsyncTask {
	//FFF
	public const EXECUTE_DROP = -114514;
	public const EXECUTE_CONTINUE = 114514;
	/** @var Threaded<callable> */
	protected Threaded $cal;
	/** @var mixed|null */
	protected $ret;

	public function __construct(PromiseInterface $promise) {
		$this->cal = $promise->getAsyncConsumer();
		$this->storeLocal('promise', $promise);
	}

	public function onRun() : void {
		while ($this->cal->count() > 0) {
			$value = $this->cal->shift();
			$this->ret = $this->serializeData($value());
			if ($this->ret === self::EXECUTE_DROP) {
				break;
			}
		}
	}

	public function serializeData($val) : string {
		return igbinary_serialize($val);
	}

	final public function onCompletion() : void {
		/** @var PromiseInterface $promise */
		$promise = $this->fetchLocal('promise');
		$data = $this->deserializeData($this->ret);
		foreach ($promise->getResultConsumer() as $consumer) {
			$consumer($data);
			if ($promise->isRejected()) {
				$promise->getRejectConsumer()(...$promise->getRejectReason());
				break;
			}
		}
	}

	/**
	 * @return mixed|null
	 */
	public function deserializeData(string $val) {
		return igbinary_unserialize($val);
	}

	final public function start() : void {
		Server::getInstance()->getAsyncPool()->submitTask($this);
	}
}
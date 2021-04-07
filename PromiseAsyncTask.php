<?php


namespace libasync;


use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;
use Threaded;

class PromiseAsyncTask extends AsyncTask {
	//FFF
	public const EXECUTE_DROP = -114514;
	public const EXECUTE_CONTINUE = 114514;
	/** @var Threaded<callable> */
	protected Threaded $cal;
	/** @var mixed|null */
	protected $ret;

	public function __construct(IPromise $promise) {
		$this->cal = $promise->getAsyncConsumer();
		$this->storeLocal([$promise]);
	}

	public function onRun() : void {
		foreach ($this->cal as $value) {
			$this->ret = $this->serializeData($value());
			if ($this->ret === self::EXECUTE_DROP) {
				break;
			}
		}
	}

	public function serializeData($val) : string {
		return igbinary_serialize($val);
	}

	final public function onCompletion(Server $server) : void {
		/** @var IPromise $promise */
		[$promise] = $this->fetchLocal();
		$data = $this->deserializeData($this->ret);
		foreach ($promise->getResultConsumer() as $consumer) {
			$consumer($data);
			if ($promise->isRejected()) {
				$promise->getRejectConsumer()(...$promise->getRejectContext());
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
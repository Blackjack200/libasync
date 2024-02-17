<?php

namespace libasync\await;

use Closure;
use GlobalLogger;
use libasync\exception\ExecutionException;
use libasync\global\GlobalAsyncRuntime;
use pmmp\thread\ThreadSafe;
use pmmp\thread\ThreadSafeArray;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\Utils;
use prokits\utils\StringFormat;
use prokits\utils\StringUtils;
use Throwable;
use const bootstrap\PRODUCTION;

class AwaitResult extends ThreadSafe {
	private ThreadSafeArray $stackTrace;
	private bool $errorHandled = false;

	/**
	 * @param \Closure():void $block
	 */
	public function __construct(
		private readonly Closure $block,
	) {
		$this->stackTrace = ThreadSafeArray::fromArray(Utils::printableCurrentTrace());
	}

	public static function empty() : static {
		return new static(static fn() => null);
	}

	public function __destruct() {
		if (!$this->errorHandled) {
			if (!PRODUCTION) {
				GlobalLogger::get()->error(
					"\n--- Await Start ---\n" .
					implode("\n", (array) $this->stackTrace) .
					"\n--- End of exception information ---"
				);
			}
			GlobalAsyncRuntime::getLoop()->add(function($break) : void {
				(new self($this->block))->panic();
				$break();
			});
		}
	}

	public function error(Closure $errorHandler) : void {
		$this->errorHandled = true;
		($this->block)($errorHandler);
	}

	/**
	 * @throws \Throwable
	 */
	public function panic() : void {
		$this->error(static fn(Throwable $thr) => throw $thr);
	}

	/**
	 * @see self::panic()
	 */
	public function logError(?Closure $do = null) : void {
		$this->error(static function(Throwable $thr) use ($do) : void {
			if (!$thr instanceof ExecutionException) {
				GlobalLogger::get()->logException($thr);
			} else {
				$thr->printWithCallTrace(GlobalLogger::get());
			}
			if ($do !== null) {
				$do($thr);
			}
		});
	}

	public function logErrorWithSender(CommandSender $sender, ?Closure $do = null) : void {
		$this->error(static function(Throwable $thr) use ($do, $sender) {
			if ($thr instanceof ExecutionException) {
				$thr->printWithCallTrace(GlobalLogger::get());
			} else {
				GlobalLogger::get()->logException($thr);
			}
			try {
				if ($sender instanceof Player && $sender->isOnline()) {
					if (class_exists(StringUtils::class)) {
						$sender->sendMessage(StringFormat::level(StringFormat::EMERGENCY)->response(false, 'async error encountered'));
					} else {
						$sender->sendMessage('async error encountered');
					}
				}
			} catch (Throwable $thr) {
				GlobalLogger::get()->logException($thr);
			}
			if ($do !== null) {
				$do($thr);
			}
		});
	}
}
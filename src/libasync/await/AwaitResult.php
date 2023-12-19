<?php

namespace libasync\await;

use Closure;
use DaveRandom\CallbackValidator\CallbackType;
use DaveRandom\CallbackValidator\ReturnType;
use GlobalLogger;
use libasync\exception\ExecutionException;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\Utils;
use prokits\utils\StringUtils;
use Throwable;
use const bootstrap\PRODUCTION;

class AwaitResult {
	private array $stackTrace;
	private bool $errorHandled = false;

	/**
	 * @param \Closure():void $block
	 */
	public function __construct(
		private readonly Closure $block,
	) {
		if (!PRODUCTION) {
			Utils::validateCallableSignature((new CallbackType(new ReturnType())), $block);
		}
		$this->stackTrace = Utils::printableCurrentTrace();
	}

	public function __destruct() {
		if (!$this->errorHandled) {
			if (!PRODUCTION) {
				GlobalLogger::get()->error(
					"\n--- Await Start ---\n" .
					implode("\n", $this->stackTrace) .
					"\n--- End of exception information ---"
				);
			}
			(new self($this->block))->panic();
		}
	}

	public function error(Closure $errorHandler) : void {
		$this->errorHandled = true;
		try {
			($this->block)();
		} catch (Throwable $thr) {
			$errorHandler($thr);
		}
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
						$sender->sendMessage(StringUtils::response(false, 'async error encountered'));
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
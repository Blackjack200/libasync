<?php

namespace libasync\await;

use libasync\exception\AsyncExceptionWrapped;
use pocketmine\command\CommandSender;
use pocketmine\utils\Utils;
use const bootstrap\PRODUCTION;

class AwaitResult {
	private array $stackTrace;
	private bool $errorHandled = false;

	/**
	 * @param \Closure(\Closure):void $do
	 */
	public function __construct(
		private readonly \Closure $innerGenerator,
		private readonly \Closure $do,
	) {
		if (!PRODUCTION) {
			Utils::validateCallableSignature(static function(\Closure $do) : void { }, $innerGenerator);
			Utils::validateCallableSignature(static function(\Closure $g) { }, $do);
		}
		$this->stackTrace = Utils::printableCurrentTrace();
	}

	public function __destruct() {
		if (!$this->errorHandled) {
			if (PRODUCTION) {
				\GlobalLogger::get()->debug(
					"\n--- Await Start ---\n" .
					implode("\n", $this->stackTrace) .
					"\n--- End of exception information ---"
				);
				$this->panic();
			} else {
				\GlobalLogger::get()->error(
					"\n--- Await Start ---\n" .
					implode("\n", $this->stackTrace) .
					"\n--- End of exception information ---"
				);
				$this->panic();
				//throw new \RuntimeException("Ignored await call");
			}
		}
	}

	public function error(\Closure $do) : void {
		$this->errorHandled = true;
		($this->do)(($this->innerGenerator)($do));
	}

	/**
	 * @throws \Throwable
	 */
	public function panic() : void {
		$this->errorHandled = true;
		($this->do)(($this->innerGenerator)(static fn(\Throwable $thr) => throw $thr));
	}

	/**
	 * @see self::panic()
	 */
	public function logError() : void {
		$this->errorHandled = true;
		($this->do)(($this->innerGenerator)(static function(\Throwable $thr) : void {
			if (!$thr instanceof AsyncExceptionWrapped) {
				\GlobalLogger::get()->logException($thr);
			} else {
				$thr->printWithCallTrace(\GlobalLogger::get());
			}
		}));
	}

	public function logErrorWithSender(CommandSender $sender, ?\Closure $do = null) : void {
		$this->errorHandled = true;
		($this->do)(($this->innerGenerator)(static function(\Throwable $thr) use ($do, $sender) {
			\GlobalLogger::get()->logException($thr);
			try {
				$sender->sendMessage("§cAwait errored.");
			} catch (AsyncExceptionWrapped $thr) {
				$thr->printWithCallTrace(\GlobalLogger::get());
			} catch (\Throwable $thr) {
				\GlobalLogger::get()->logException($thr);
			}
			$do($thr);
		}));
	}
}
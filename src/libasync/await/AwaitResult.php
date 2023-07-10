<?php

namespace libasync\await;

use Generator;
use pocketmine\utils\Utils;
use const bootstrap\PRODUCTION;

class AwaitResult {
	private array $stackTrace;
	private bool $errorHandled = false;

	/**
	 * @param \Closure(Generator):void $do
	 */
	public function __construct(
		private readonly \Closure $innerGenerator,
		private readonly \Closure $do,
	) {
		if (!PRODUCTION) {
			Utils::validateCallableSignature(static function(\Closure $do) : Generator { }, $innerGenerator);
			Utils::validateCallableSignature(static function(Generator $g) { }, $do);
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
				throw new \RuntimeException("Ignored await call");
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
		($this->do)(($this->innerGenerator)(static fn(\Throwable $thr) => \GlobalLogger::get()->logException($thr)));
	}
}
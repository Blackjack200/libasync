<?php

namespace libasync\await;

use Closure;
use GlobalLogger;
use libasync\exception\ExecutionException;
use libasync\global\GlobalAsyncRuntime;
use libasync\utils\ClosureUtils;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use pocketmine\utils\Utils;
use prokits\utils\StringFormat;
use Throwable;
use const bootstrap\PRODUCTION;

class AwaitResult {
	protected bool $join = true;
	private array $stackTrace;
	private bool $errorHandled = false;

	/**
	 * @param Closure(?Closure $errorHandler,bool $joined):Coroutine $createCoroutine
	 * @param Closure(Coroutine $coroutine):void $onCreation
	 */
	public function __construct(
		private readonly Closure $createCoroutine,
		private readonly Closure $onCreation
	) {
		if (!PRODUCTION) {
			ClosureUtils::noCyclic($this->createCoroutine, $this->onCreation);
			ClosureUtils::noCyclic($this->createCoroutine, $this);
			ClosureUtils::noCyclic($this->onCreation, $this);
		}
		//skip __construct
		$this->stackTrace = Utils::printableCurrentTrace(1);
	}

	public function __destruct() {
		if ($this->errorHandled) {
			return;
		}

		$stackTrace = $this->stackTrace;
		$createCoroutine = $this->createCoroutine;
		$onCreation = $this->onCreation;

		if (!PRODUCTION) {
			GlobalLogger::get()->error(
				"\n--- Await Start ---\n" .
				implode("\n", $stackTrace) .
				"\n--- End of exception information ---"
			);
		}

		GlobalAsyncRuntime::getLoop()->add(static function($break) use ($createCoroutine, $onCreation) : void {
			(new self($createCoroutine, $onCreation))->panic();
			$break();
		});
	}

	/**
	 * @param ?Closure(Throwable):void $errorHandler
	 */
	public function error(?Closure $errorHandler) : void {
		$this->errorHandled = true;
		$coroutine = ($this->createCoroutine)($errorHandler, $this->join);
		($this->onCreation)($coroutine);
	}

	public function panic() : void {
		$this->error(null);
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
					if (class_exists(StringFormat::class)) {
						$sender->sendMessage(StringFormat::error('async error encountered'));
					} else {
						$sender->sendMessage(TextFormat::DARK_RED . 'async error encountered');
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

	public function join() : self {
		$this->join = true;
		return $this;
	}

	public function mayDrop() : self {
		$this->join = false;
		return $this;
	}
}

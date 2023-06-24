<?php

namespace libasync\exception;

use libasync\utils\Utils;
use Logger;
use pocketmine\utils\Utils as PMMPUtils;
use Throwable;

final class AsyncExecutionException {
	private function __construct(
		protected string $class,
		protected string $message,
		protected string $traceSerialized,
		protected int    $code,
		protected string $file,
		protected int    $line
	) {
	}

	public static function from(
		string $class,
		string $message,
		array  $trace,
		int    $code,
		string $file,
		int    $line
	) : self {
		return new self(
			$class, $message, igbinary_serialize($trace), $code, $file, $line
		);
	}

	public static function wrap(Throwable $thr) : self {
		return new self($thr::class, $thr->getMessage(), igbinary_serialize(PMMPUtils::printableTrace($thr->getTrace())), $thr->getCode(), $thr->getFile(), $thr->getLine());
	}

	public function is(string $class) : bool {
		return is_subclass_of($this->class, $class) || $this->class === $class;
	}

	public function __toString() {
		return 'Nop';
	}

	public function print(Logger $logger) : void {
		$logger->critical(Utils::printPromiseExceptionMessage($this));
		foreach ($this->getTrace() as $line) {
			$logger->critical($line);
		}
	}

	public function printWithCallTrace(Logger $logger, array $callTrace) : void {
		$logger->critical(Utils::printPromiseExceptionMessage($this));
		$logger->critical(
			"\n--- Stack trace ---\n" .
			implode("\n", $this->getTrace()) .
			"\n--- End of exception information ---"
		);
		$logger->critical(
			"\n--- Call Stack trace ---\n" .
			implode("\n", $callTrace) .
			"\n--- End of exception information ---"
		);
	}


	public function getMessage() : string {
		return $this->message;
	}

	public function getCode() : int {
		return $this->code;
	}

	public function getFile() : string {
		return $this->file;
	}

	public function getLine() : int {
		return $this->line;
	}

	public function getClass() : string {
		return $this->class;
	}

	public function getTrace() : array {
		return igbinary_unserialize($this->traceSerialized);
	}
}
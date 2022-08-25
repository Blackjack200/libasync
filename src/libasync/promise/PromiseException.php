<?php

namespace libasync\promise;

use libasync\utils\Utils;
use Logger;
use Throwable;

final class PromiseException {
	private function __construct(
		protected string $class,
		protected string $message,
		protected array  $trace,
		protected int $code,
		protected string $file,
		protected int $line
	) {
	}

	public static function from(array $arr) : self {
		return new self(...$arr);
	}

	public static function wrap(Throwable $thr, array $trace) : self {
		return new self($thr::class, $thr->getMessage(), $trace, $thr->getCode(), $thr->getFile(), $thr->getLine());
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
		return $this->trace;
	}
}
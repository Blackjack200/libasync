<?php

namespace libasync\promise;

use libasync\utils\Utils;
use Logger;
use pmmp\thread\ThreadSafe;
use pmmp\thread\ThreadSafeArray;
use Throwable;

final class PromiseException extends ThreadSafe {
	private function __construct(
		protected string $class,
		protected string $message,
		protected ThreadSafeArray  $trace,
		protected int $code,
		protected string $file,
		protected int $line
	) {
	}

	public static function from(ThreadSafeArray $arr) : self {
		return new self(...$arr);
	}

	public static function wrap(Throwable $thr, ThreadSafeArray $trace) : self {
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

	public function getTrace() : ThreadSafeArray {
		return $this->trace;
	}
}
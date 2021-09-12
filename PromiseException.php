<?php

namespace libasync;

use Error;
use InvalidArgumentException;
use Logger;
use pocketmine\errorhandler\ErrorTypeToStringMap;
use pocketmine\utils\Filesystem;
use Throwable;

final class PromiseException extends Error {
	private function __construct(
		protected string $class,
		string           $message,
		protected array  $trace,
		int              $code,
		string           $file,
		int              $line
	) {
		parent::__construct($message, $code, null);
		$this->file = $file;
		$this->line = $line;
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

	public function getClass() : string {
		return $this->class;
	}

	public function getRealTrace() : array {
		return $this->trace;
	}

	public function __toString() {
		return 'Nop';
	}

	private static function printExceptionMessage(PromiseException $e) : string {
		$errstr = preg_replace('/\s+/', ' ', trim($e->getMessage()));

		$errno = $e->getCode();
		if (is_int($errno)) {
			try {
				$errno = ErrorTypeToStringMap::get($errno);
			} catch (InvalidArgumentException $ex) {
				//pass
			}
		}

		$errfile = Filesystem::cleanPath($e->getFile());
		$errline = $e->getLine();

		return $e->getClass() . ": \"$errstr\" ($errno) in \"$errfile\" at line $errline";
	}

	public function print(Logger $logger) : void {
		$logger->critical(self::printExceptionMessage($this));
		foreach ($this->getRealTrace() as $line) {
			$logger->critical($line);
		}
	}
}
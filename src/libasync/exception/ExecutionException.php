<?php

namespace libasync\exception;

use Logger;

class ExecutionException extends \Exception {
	public function __construct(
		private readonly ExecutionExceptionWrapper $exception,
		private readonly array                     $callTrace,
	) {
		parent::__construct($this->exception->getMessage(), $this->exception->getCode());
	}

	public function getException() : ExecutionExceptionWrapper { return $this->exception; }

	public function printWithCallTrace(?Logger $logger = null) : void {
		$this->exception->printWithCallTrace($this->callTrace, $logger);
	}
}
<?php

namespace libasync\exception;

use Logger;

class AsyncExceptionWrapped extends \Exception {
	public function __construct(
		private readonly AsyncExecutionException $exception,
		private readonly array                   $callTrace,
	) {
		parent::__construct($this->exception->getMessage(), $this->exception->getCode());
	}

	public function getException() : AsyncExecutionException { return $this->exception; }

	public function printWithCallTrace(?Logger $logger = null) : void {
		$this->exception->printWithCallTrace($this->callTrace, $logger);
	}
}
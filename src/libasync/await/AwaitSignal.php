<?php

namespace libasync\await;

/**
 * @internal
 */
enum AwaitSignal {
	case SIG_WAIT;
	case SIG_INTERRUPT;
	case SIG_FINISH;
	case SIG_EXCEPTION;
	case SIG_SET_TRACE;
	case SIG_SET_RECEIPT;
}
<?php

namespace libasync\await;

enum AwaitSignal {
	case SIG_WAIT;
	case SIG_INTERRUPT;
	case SIG_FINISH;
	case SIG_SET_TRACE;
}
<?php

namespace libasync\utils;

final class TimeUnits {
	private function __construct() { }

	public const NANO = 1;
	public const MICRO_IN_NANO = 1000;
	public const SECOND_IN_NANO = 1000 * self::MICRO_IN_NANO;

	public const TICK_IN_NANO = self::SECOND_IN_NANO / 20;

}
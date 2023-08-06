<?php

namespace libasync\utils;

use pmmp\thread\ThreadSafe;

final class Utils {
	public static function smartSerialize(mixed $m) : ThreadSafe|null|string {
		if ($m instanceof ThreadSafe) {
			return $m;
		}
		return igbinary_serialize($m);
	}

	public static function smartDeserialize(mixed $m) : mixed {
		if ($m instanceof ThreadSafe) {
			return $m;
		}
		return igbinary_unserialize($m);
	}
}
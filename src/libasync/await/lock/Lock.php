<?php

namespace libasync\await\lock;

interface Lock {
	public function lock() : \Generator;

	public function unlock() : void;
}
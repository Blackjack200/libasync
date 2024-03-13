<?php

namespace libasync\pool;


use libasync\utils\ResourceRef;

/**
 * @template T
 */
interface ResourcePoolInterface {
	/**
	 * @return ResourceRef<T>|null
	 */
	public function select() : ?ResourceRef;

	public function close() : void;
}
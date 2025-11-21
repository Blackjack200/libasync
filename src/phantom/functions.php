<?php

namespace phantom;

/**
 * @template T of object
 * @param T $obj
 * @return Weak<T>
 */

function weak(object $obj) : Weak {
	return new Weak($obj);
}

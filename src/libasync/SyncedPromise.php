<?php


namespace libasync;


class SyncedPromise extends Promise {
	protected string $class = SyncedPromiseTask::class;
}
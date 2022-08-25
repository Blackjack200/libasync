<?php


namespace libasync\promise;


use libasync\promise\task\SyncedPromiseTask;

class SyncedPromise extends Promise {
	protected string $class = SyncedPromiseTask::class;
}
<?php

/**
 * Runs tasks on a queue
 */
interface TaskRunnerEngine {

	/**
	 * Run tasks on the given queue
	 *
	 * @param string $queue
	 */
	public function runQueue($queue);

	/**
	 * Run a single job
	 *
	 * @param int $id
	 */
	public function runJob($id);
}

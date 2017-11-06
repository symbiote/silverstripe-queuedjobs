<?php

/**
 * execute jobs immediately in the current request context
 *
 * @author marcus@silverstripe.com.au
 * @license BSD License http://silverstripe.org/bsd-license/
 */
class ImmediateQueueHandler {
	/**
	 * @var array
	 */
	private static $dependencies = array(
		'queuedJobService' => '%$QueuedJobService',
	);

	/**
	 * @var QueuedJobService
	 */
	public $queuedJobService;

	/**
	 * @param QueuedJobDescriptor $job
	 */
	public function startJobOnQueue(QueuedJobDescriptor $job) {
		$this->queuedJobService->runJob($job->ID);
	}

	public function scheduleJob(QueuedJobDescriptor $job, $date) {
		$this->queuedJobService->runJob($job->ID);
	}
}

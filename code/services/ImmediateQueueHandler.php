<?php

/**
 * execute jobs immediately in the current request context
 *
 * @author marcus@silverstripe.com.au
 * @license BSD License http://silverstripe.org/bsd-license/
 */
class ImmediateQueueHandler {
	public static $dependencies = array(
		'queuedJobService' => '%$QueuedJobService',
	);
	/**
	 * @var QueuedJobService
	 */
	public $queuedJobService;
	
	public function startJobOnQueue(QueuedJobDescriptor $job) {
		$this->queuedJobService->runJob($job->ID);
	}
}

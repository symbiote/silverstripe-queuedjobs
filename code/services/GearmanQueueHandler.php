<?php

/**
 * @author marcus@silverstripe.com.au
 * @license BSD License http://silverstripe.org/bsd-license/
 */
class GearmanQueueHandler {

	private static $dependencies = array(
		'gearmanService' => '%$GearmanService'
	);

	/**
	 * @var GearmanService
	 */
	public $gearmanService;

	public function startJobOnQueue(QueuedJobDescriptor $job) {
		$this->gearmanService->jobqueueExecute($job->ID);
	}

	public function scheduleJob(QueuedJobDescriptor $job, $date) {
		$this->gearmanService->sendJob('scheduled', 'jobqueueExecute', array($job->ID), strtotime($date));
	}
}

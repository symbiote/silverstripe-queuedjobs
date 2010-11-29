<?php

/**
 * Interface definition for a queued job
 *
 * @author Marcus Nyeholt <marcus@silverstripe.com.au>
 * @license BSD http://silverstripe.org/bsd-license/
 */
interface QueuedJob {

	// always run immediate jobs as soon as possible
	const IMMEDIATE = '1';
	// queued jobs may have some processing to do, but should be pretty quick
	const QUEUED = '2';
	// large jobs will take minutes, not seconds to run
	const LARGE = '3';

	const STATUS_NEW = 'New';
	const STATUS_INIT = 'Initialising';
	const STATUS_RUN = 'Running';
	const STATUS_WAIT = 'Waiting';
	const STATUS_COMPLETE = 'Complete';
	const STATUS_PAUSED = 'Paused';
	const STATUS_CANCELLED = 'Cancelled';
	const STATUS_BROKEN = 'Broken';

	/**
	 * Gets a title for the job that can be used in listings
	 */
	public function getTitle();

	/**
	 * Gets a unique signature for this job and its current parameters.
	 *
	 * This is used so that a job isn't added to a queue multiple times - this for example, an indexing job
	 * might be added every time an item is saved, but it isn't processed immediately. We dont NEED to do the indexing
	 * more than once (ie the first indexing will still catch any subsequent changes), so we don't need to have
	 * it in the queue more than once.
	 *
	 * If you have a job that absolutely must run multiple times, the AbstractQueuedJob class provides a time sensitive
	 * randomSignature() method that can be used for returning a random signature each time
	 */
	public function getSignature();

	/**
	 * Setup this queued job. This is only called the first time this job is executed
	 * (ie when currentStep is 0)
	 *
	 */
	public function setup();

	/**
	 * Called whenever a job is restarted for whatever reason.
	 *
	 * This is a separate method so that broken jobs can do some fixup before restarting. 
	 */
	public function prepareForRestart();

	/**
	 * What type of job is this? Options are
	 *
	 */
	public function getJobType();

	/**
	 * A job is run within an external processing loop that will call this method while there are still steps left
	 * to complete in the job.
	 *
	 * Typically, this method should process just a small amount of data - after calling this method, the process
	 * loop will save the current state of the job to protect against potential failures or errors.
	 */
	public function process();

	/**
	 * Returns true or false to indicate that this job is finished
	 */
	public function jobFinished();

	/**
	 * Return the current job state as an object containing data
	 *
	 * stdClass (
	 *		'totalSteps' => the total number of steps in this job - this is relayed to the user as an indicator of time
	 *		'currentStep' => the current number of steps done so far.
	 *		'isComplete' => whether the job is finished yet
	 *		'jobData' => data that the job wants persisted when it is stopped or started
	 *		'messages' => a cumulative array of messages that have occurred during this job so far
	 * )
	 */
    public function getJobData();

	/**
	 * Sets data about the job
	 *
	 * is an inverse of the getJobData() method, but being explicit about what data is set
	 *
	 * @see QueuedJob::getJobData();
	 */
	public function setJobData($totalSteps, $currentStep, $isComplete, $jobData, $messages);

	
	/**
	 * Add an arbitrary text message into a job
	 *
	 * @param String $message 
	 */
	public function addMessage($message);
}
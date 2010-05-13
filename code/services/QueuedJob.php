<?php
/*

Copyright (c) 2009, SilverStripe Australia PTY LTD - www.silverstripe.com.au
All rights reserved.

Redistribution and use in source and binary forms, with or without modification, are permitted provided that the following conditions are met:

    * Redistributions of source code must retain the above copyright notice, this list of conditions and the following disclaimer.
    * Redistributions in binary form must reproduce the above copyright notice, this list of conditions and the following disclaimer in the
      documentation and/or other materials provided with the distribution.
    * Neither the name of SilverStripe nor the names of its contributors may be used to endorse or promote products derived from this software
      without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE
LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE
GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT,
STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY
OF SUCH DAMAGE.
*/

/**
 * Interface definition for a queued job
 *
 * @author Marcus Nyeholt <marcus@silverstripe.com.au>
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
	 * Setup this queued job
	 */
	public function setup();

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
?>
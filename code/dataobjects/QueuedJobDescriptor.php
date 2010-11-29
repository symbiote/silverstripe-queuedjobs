<?php

/**
 * A QueuedJobDescriptor is the stored representation of a piece of work that could take a while to execute,
 * because of which it is desireable to not have it executing in parallel to other jobs.
 *
 * A queued job should always attempt to report how many potential dataobjects will be affected by being executed;
 * this will determine which queue it is placed within so that some shorter jobs can execute immediately without needing
 * to wait for a potentially long running job.
 *
 * @author Marcus Nyeholt <marcus@silverstripe.com.au>
 * @license BSD http://silverstripe.org/bsd-license/
 */
class QueuedJobDescriptor extends DataObject
{
    public static $db = array(
		'JobTitle' => 'Varchar(255)',
		'Signature' => 'Varchar(64)',
		'Implementation' => 'Varchar(64)',
		'StartAfter' => 'SS_Datetime',
		'JobStarted' => 'SS_Datetime',
		'JobRestarted' => 'SS_Datetime',
		'JobFinished' => 'SS_Datetime',
		'TotalSteps' => 'Int',
		'StepsProcessed' => 'Int',
		'LastProcessedCount' => 'Int',
		'ResumeCounts' => 'Int',
		'SavedJobData' => 'Text',
		'SavedJobMessages' => 'Text',
		'JobStatus' => 'Varchar(16)',
		'JobType' => 'Varchar(16)',
	);

	public static $has_one = array(
		'RunAs' => 'Member',
	);

	public static $defaults = array(
		'JobStatus' => 'New',
		'ResumeCounts' => 0,
	);

	public function pause() {
		if ($this->JobStatus == QueuedJob::STATUS_WAIT || $this->JobStatus == QueuedJob::STATUS_RUN) {
			$this->JobStatus = QueuedJob::STATUS_PAUSED;
			$this->write();
		}
		
	}

	public function resume() {
		if ($this->JobStatus == QueuedJob::STATUS_PAUSED || $this->JobStatus == QueuedJob::STATUS_BROKEN) {
			$this->JobStatus = QueuedJob::STATUS_WAIT;
			$this->ResumeCounts++;
			$this->write();
		}
	}
	
	public function execute() {
		$service = singleton('QueuedJobService');
		$service->runJob($this->ID);
	}

	public function getMessages() {
		if (strlen($this->SavedJobMessages)) {
			$msgs = json_decode($this->SavedJobMessages);
			return '<ul><li>'.implode('</li><li>', $msgs).'</li></ul>';
		}
	}
}
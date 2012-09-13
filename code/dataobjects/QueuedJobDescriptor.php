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
class QueuedJobDescriptor extends DataObject {
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
	
	
	public static $searchable_fields = array(
		'JobTitle',
	);
	
	public function requireDefaultRecords() {
		parent::requireDefaultRecords();
		$this->getJobDir();
	}
	
	
	public function summaryFields() {
		$columns = array(
			'JobTitle' => _t('QueuedJobs.TABLE_TITLE', 'Title'),
			'Created' => _t('QueuedJobs.TABLE_ADDE', 'Added'),
			'JobStarted' => _t('QueuedJobs.TABLE_STARTED', 'Started'),
//			'JobRestarted' => _t('QueuedJobs.TABLE_RESUMED', 'Resumed'),
			'StartAfter' => _t('QueuedJobs.TABLE_START_AFTER', 'Start After'),
			'JobType'	=> _t('QueuedJobs.JOB_TYPE', 'Job Type'),
			'JobStatus' => _t('QueuedJobs.TABLE_STATUS', 'Status'),
			'Messages' => _t('QueuedJobs.TABLE_MESSAGES', 'Message'),
			'StepsProcessed' => _t('QueuedJobs.TABLE_NUM_PROCESSED', 'Done'),
			'TotalSteps' => _t('QueuedJobs.TABLE_TOTAL', 'Total'),
		);
		return $columns;
	}
	
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
			$this->activateOnQueue();
		}
	}

	/**
	 * Called to indicate that the job is ready to be run on the queue. This is done either as the result of
	 * creating the job and adding it, or when resuming. 
	 */
	public function activateOnQueue() {
		// if it's an immediate job, lets cache it to disk to be picked up later
		if ($this->JobType == QueuedJob::IMMEDIATE && !QueuedJobService::$use_shutdown_function) {
			touch($this->getJobDir() . '/' . 'queuedjob-' . $this->ID);
		}
	}

	/**
	 * Gets the path to the queuedjob cache directory
	 */
	protected function getJobDir() {
		// make sure our temp dir is in place. This is what will be inotify watched
		$jobDir = QueuedJobService::$cache_dir;
		if ($jobDir{0} != '/') {
			$jobDir = getTempFolder() . '/' . $jobDir;
		}

		if (!is_dir($jobDir)) {
			Filesystem::makeFolder($jobDir);
		}
		return $jobDir;
	}
	
	public function execute() {
		$service = singleton('QueuedJobService');
		$service->runJob($this->ID);
	}

	/**
	 * Called when the job has completed and we want to cleanup anything the descriptor has lying around
	 * in caches or the like. 
	 */
	public function cleanupJob() {
		// remove the job's temp file if it exists
		$tmpFile = $this->getJobDir() . '/' . 'queuedjob-' . $this->ID;
		if (file_exists($tmpFile)) {
			unlink($tmpFile);
		}
	}

	public function onBeforeDelete() {
		parent::onBeforeDelete();
		$this->cleanupJob();
	}

	public function getMessages() {
		if (strlen($this->SavedJobMessages)) {
			$msgs = @unserialize($this->SavedJobMessages);
			return is_array($msgs) ? '<ul><li>'.implode('</li><li>', $msgs).'</li></ul>' : '';
		}
	}
}
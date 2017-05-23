<?php

/**
 * An queued job to clean out the QueuedJobDescriptor Table
 * which often gets too full
 *
 * @author Andrew Aitken-Fincham <andrew@silverstripe.com.au>
 */
class CleanupJob extends AbstractQueuedJob implements QueuedJob {

	/**
	 * How we will determine "stale"
	 * Possible values: age, number
	 * @config
	 * @var string
	 */
	private static $cleanup_method = "age";

	/**
	 * Value associated with cleanupMethod
	 * age => days, number => integer
	 * @config
	 * @var integer
	 */
	private static $cleanup_value = 30;

	/**
	 * Which JobStatus values are OK to be deleted
	 * @config
	 * @var array
	 */
	private static $cleanup_statuses = array(
		"Complete",
		"Broken",
		// "Initialising",
		// "Running",
		// "New",
		// "Paused",
		// "Cancelled",
		// "Waiting",
	);

	/**
	 * Check whether is enabled or not for BC
	 * @config
	 * @var boolean
	 */
	private static $is_enabled = false;

	/**
	 * Required because we aren't extending object
	 * @return Config_ForClass
	 */
	public function config() {
		return Config::inst()->forClass(get_called_class());
	}

	/**
	 * Defines the title of the job
	 * @return string
	 */
	public function getTitle() {
		return _t(
			'CleanupJob.Title',
			"Clean up old jobs from the database"
		);
	}

	/**
	 * Set immediacy of job
	 * @return int
	 */
	public function getJobType() {
		$this->totalSteps = '1';
		return QueuedJob::IMMEDIATE;
	}

	/**
	 * Clear out stale jobs based on the cleanup values
	 */
	public function process() {
		$statusList = implode('\', \'', $this->config()->cleanup_statuses);
		switch($this->config()->cleanup_method) {
			// If Age, we need to get jobs that are at least n days old
			case "age":
				$cutOff = date("Y-m-d H:i:s",
					strtotime(SS_Datetime::now() .
						" - " .
						$this->config()->cleanup_value .
						" days"
					)
				);
				$stale = DB::query(
					'SELECT "ID" 
					FROM "QueuedJobDescriptor" 
					WHERE "JobStatus" 
					IN (\'' . $statusList . '\')
					AND "LastEdited" < \'' . $cutOff .'\''
				);
				$staleJobs = $stale->column("ID");
				break;
			// If Number, we need to save n records, then delete from the rest
			case "number":
				$fresh = DB::query(
					'SELECT "ID" 
					FROM "QueuedJobDescriptor" 
					ORDER BY "LastEdited" 
					ASC LIMIT ' . $this->config()->cleanup_value
				);
				$freshJobIDs = implode('\', \'', $fresh->column("ID"));

				$stale = DB::query(
					'SELECT "ID" 
					FROM "QueuedJobDescriptor" 
					WHERE "ID" 
					NOT IN (\'' . $freshJobIDs . '\') 
					AND "JobStatus" 
					IN (\'' . $statusList . '\')'
				);
				$staleJobs = $stale->column("ID");
				break;
			default:
				$this->addMessage("Incorrect configuration values set. Cleanup ignored");
				$this->isComplete = true;
				return;
		}
		if (empty($staleJobs)) {
			$this->addMessage("No jobs to clean up.");
			$this->isComplete = true;
			$this->reenqueue();
			return;
		}
		$numJobs = count($staleJobs);
		$staleJobs = implode('\', \'', $staleJobs);
		DB::query('DELETE FROM "QueuedJobDescriptor"
			WHERE "ID"
			IN (\'' . $staleJobs . '\')'
		);
		$this->addMessage($numJobs . " jobs cleaned up.");
		$this->reenqueue();
		$this->isComplete = true;
		return;
	}

	private function reenqueue()
	{
		if (Config::inst()->get('CleanupJob', 'is_enabled')) {
			$this->addMessage("Queueing the next Cleanup Job.");
			$cleanup = new CleanupJob();
			singleton('QueuedJobService')->queueJob($cleanup, date('Y-m-d H:i:s', time() + 86400));
		}
	}

}

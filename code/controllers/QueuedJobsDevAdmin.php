<?php

class QueuedJobsDevAdmin extends Controller {
	private static $url_handlers = array(
		'' => 'index',
		'$JobClassName!/$ID' => 'job'
	);

	private static $allowed_actions = array(
		'index',
		'job',
	);

	/**
	 * @var array
	 */
	private static $dependencies = array(
		'jobQueue' => '%$QueuedJobService',
	);

	public function init() {
		parent::init();

		$isRunningTests = (class_exists('SapphireTest', false) && SapphireTest::is_running_test());
		$canAccess = (
			Director::isDev()
			|| (Director::is_cli() && !$isRunningTests)
			|| Permission::check("ADMIN")
		);
		if(!$canAccess) return Security::permissionFailure($this);

		if (!$this->jobQueue) {
			$this->jobQueue = singleton('QueuedJobService');
		}
	}

	/**
	 * Browse all queued job types
	 */
	public function index() {
		$jobs = $this->getJobs();

		if(!Director::is_cli()) {
			$this->echoTemplate(array(
				'Jobs' => new ArrayList($jobs),
			));
		} else {
			echo "SILVERSTRIPE DEVELOPMENT TOOLS: Jobs\n--------------------------\n\n";
			foreach($jobs as $job) {
				echo " * ".$job['Count']." ".$job['Title']." job(s): sake dev/jobs/" . $job['Segment'] . "\n";
			}
		}
	}

	public function job($request) {
		$classSegment = $request->param('JobClassName');
		$class = str_replace('-', '\\', $classSegment);
		if (!$class) {
			return $this->echoTemplate(array(
				'Content' => 'Cannot provide blank class name',
			));
		}
		if (!class_exists($class)) {
			// NOTE(Jake): Would be nice to make a "best" guess here or add some
			//			   sort of partial match execute for command-line UX
			return $this->echoTemplate(array(
				'Content' => 'Class "'.$class.'" does not exist.',
			));
		}
		$list = $this->getJobList()->filter(array('Implementation' => $class));

		$actionOrID = $request->param('ID');
		if (!$actionOrID) {
			// View job list
			$segment = $classSegment.'/any';
			$jobs = array(
				array(
					'Title' => 'Execute "any" job (creates and executes a job if none exist)',
					'Description' => 'Will execute any item on this list, if none exist, this will create a new job of this type and immediately execute.',
					'Segment' => $segment,
					'Link' => $this->Link($segment),
				)
			);
			foreach ($list->sort('ID') as $jobRecord) {
				$segment = $classSegment.'/'.$jobRecord->ID;
				// Show step status if it's used by the job
				$stepStatus = '';
				if ($jobRecord->TotalSteps) {
					$stepStatus = ' (Step: '.(int)$this->StepsProcessed.'/'.(int)$jobRecord->TotalSteps.')';
				}
				$jobs[] = array(
					'Title' => 'Execute #'.$jobRecord->ID.' '.$jobRecord->Implementation,
					'Description' => $jobRecord->getTitle().$stepStatus,
					'Segment' => $segment,
					'Link' => $this->Link($segment),
				);
			}
			if (!Director::is_cli()) {
				return $this->echoTemplate(array(
					'Records' => new ArrayList($jobs),
				), 'QueuedJobsDevAdmin_recordlist');
			}
			foreach($jobs as $job) {
				echo " * ".$job['Title']." : sake dev/jobs/" . $job['Segment'] . "\n";
			}
			return;
		}
		if (is_numeric($actionOrID)) {
			$job = $list->byID($actionOrID);
			if (!$job || !$job->exists()) {
				return $this->echoTemplate(array(
					'Content' => 'Cannot find job by ID #'.$actionOrID.' of class "'.$class.'"'
				));
			}
			if (Director::is_cli()) {
				echo "Starting existing job #$job->ID $job->Implementation \n";
			}
			return $this->executeJobAndRender($job->ID, 
					'Successfully executed existing job #{$ID} "{$Class}"',
					'Broken job #{$ID} "{$Class}"');
		}
		switch ($actionOrID) {
			case "any":
				$job = $list->sort('ID')->first();
				if ($job) {
					if (Director::is_cli()) {
						echo "Starting existing job #$job->ID $job->Implementation \n";
					}
					return $this->executeJobAndRender($job->ID, 
					'Successfully ran existing job #{$ID} "{$Class}"',
					'Broken existing job #{$ID} "{$Class}"');
				}
				// If no items exist, create one with default parameters and execute.
				$job = method_exists($class, 'create') ? $class::create() : new $class;
				$jobID = $this->jobQueue->queueJob($job, $time = null);
				if (!$jobID) {
					return $this->echoTemplate(array(
						'Content' => 'Failed to queue job '.$class.'.',
					));
				}
				if (Director::is_cli()) {
					echo "Starting new job #$job->ID $job->Implementation \n";
				}
				return $this->executeJobAndRender($jobID, 
					'Successfully created and executed new job #{$ID} "{$Class}"', 
					'Broken new job #{$ID} "{$Class}"');
			break;
		}
		return $this->echoTemplate(array(
			'Content' => 'Action "'.$actionOrID.'" is invalid',
		));
	}

	/**
	 * Execute a job and echo / render the results
	 */
	protected function executeJobAndRender($jobID, $successMessage, $brokeMessage) {
		$isSuccessful = false;
		$echoMessages = '';
		if (Director::is_cli()) {
			$isSuccessful = $this->jobQueue->runJob($jobID);
		} else {
			ob_start();
			$isSuccessful = $this->jobQueue->runJob($jobID);
			$echoMessages = ob_get_contents();
			ob_end_clean();
		}
		$jobRecord = QueuedJobDescriptor::get()->byID($jobID);
		$jobRecordID = $jobRecord->ID ? $jobRecord->ID : 0;
		$jobRecordClass = $jobRecord->Implementation ? $jobRecord->Implementation : '';
		$message = ($isSuccessful) ? $successMessage : $brokeMessage;
		$message = str_replace('{$ID}', $jobRecordID, $message);
		$message = str_replace('{$Class}', $jobRecordClass, $message);
		if (Director::is_cli()) {
			return $this->echoTemplate(array(
				'Content' => $message,
			));
			return;
		}
		return $this->echoTemplate(array(
			'EchoMessage' => $echoMessages,
			'Content' => $message,
		));
	}

	/** 
	 * Gets a list of all the current jobs
	 *
	 * @return DataList
	 */
	protected function getJobList() {
		return $this->jobQueue->getJobList();
	}

	/**
	 * @return array
	 */
	protected function getJobs() {
		$queuedJobClasses = ClassInfo::subclassesFor('AbstractQueuedJob');
		unset($queuedJobClasses['AbstractQueuedJob']);
		asort($queuedJobClasses);

		// Count how many instances of a class type is queued and incomplete
		$ids = $this->getJobList()->column('ID');
		$queuedJobExistsCount = DB::prepared_query(
			'SELECT "Implementation", COUNT(*) AS "count" FROM QueuedJobDescriptor WHERE "ID" IN ('.DB::placeholders($ids).') GROUP BY "Implementation"', 
			$ids
		);
		$queuedJobExistsCount = $queuedJobExistsCount->map('Implementation', 'count');

		$result = array();
		foreach ($queuedJobClasses as $class => $_) {
			$title = $class;

			$desc = '';
			if (!$desc) {
				// Handle 'private static $description = "My Value"'
				$desc = Config::inst()->get($class, 'description');
				if (!$desc) {
					// Handle 'protected $description = "My Value"'
					$reflectionClass = new ReflectionClass($class);
					$defaultProps = $reflectionClass->getDefaultProperties();
					if (isset($defaultProps['description'])) {
						$desc = $defaultProps['description'];
					}
				}
			}
			$desc = nl2br($desc);
			$segment = str_replace('\\', '-', $class);
			if (Director::is_cli()) {
				$title = Convert::html2raw($title);
				$desc = Convert::html2raw($desc);
			}

			$result[] = array(
				'Class' => $class,
				'Title' => $title,
				'Segment' => $segment,
				'Description' => $desc,
				'Count' => isset($queuedJobExistsCount[$class]) ? (int)$queuedJobExistsCount[$class] : 0,
				'Link' => $this->Link($segment),
			);
		}
		$this->extend('updateJobs', $result);
		return $result;
	}

	protected function echoTemplate($customise = array(), $templateName = '') {
		if (Director::is_cli()) {
			echo $customise['Content'];
			return;
		}

		if (!$templateName) {
			$templateName = 'QueuedJobsDevAdmin';
		}
		$customise = array_merge(array(
			'AbsoluteBaseURL' => Director::absoluteBaseURL(),
		), $customise);

		$oldEnabled = Config::inst()->get('SSViewer', 'theme_enabled');
		Config::inst()->update('SSViewer', 'theme_enabled', false);
		$result = $this->customise($customise)->renderWith($templateName);
		Config::inst()->update('SSViewer', 'theme_enabled', $oldEnabled);

		$renderer = new DebugView;
		$renderer->writeHeader();
		$renderer->writeInfo("SilverStripe Development Tools: Jobs", Director::absoluteBaseURL());
		echo $result;
		$renderer->writeFooter();
	}

	public function Link($action = '') {
		return Director::absoluteBaseURL().'dev/jobs/'.$action;
	}
}

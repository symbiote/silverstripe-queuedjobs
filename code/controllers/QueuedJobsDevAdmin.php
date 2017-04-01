<?php

class QueuedJobsDevAdmin extends Controller {
	private static $url_handlers = array(
		'' => 'index',
		'$JobClassName!/$ID' => 'runjob'
	);

	private static $allowed_actions = array(
		'index',
		'runjob',
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
			// We need to ensure that DevelopmentAdminTest can simulate permission failures when running
			// "dev/jobs" from CLI.
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

	public function runjob($request) {
		$class = $request->param('JobClassName');
		$class = str_replace('-', '\\', $class);
		if (!class_exists($class)) {
			//$this->getJobs()->filter(array('Implementation:StartsWith' => $class));
			return $this->echoTemplate(array(
				'Content' => 'Class "'.$class.'" does not exist.',
			));
		}
		$list = $this->getJobList()->filter(array('Implementation' => $class));

		$id = (int)$request->param('ID');
		if ($id) {
			$job = $list->byID($id);
			if (!$job || !$job->exists()) {
				return $this->echoTemplate(array(
					'Content' => 'Cannot find job by ID #'.$id.' of class "'.$class.'"'
				));
			}
			return $this->executeJob($job->ID, 
					'Ran existing job #{$ID} {$Class} SUCCESSFULLY',
					'Ran existing job #{$ID} {$Class} but BROKE');
		}

		$existingCount = $list->count();
		if ($existingCount == 0) {
			$params = array();
			$time = null;

			$reflection = new ReflectionClass($class);
			$job = $reflection->newInstanceArgs($params);
			$jobID = $this->jobQueue->queueJob($job, $time = null);
			if (!$jobID) {
				return $this->echoTemplate(array(
					'Content' => 'Failed to queue job '.$class.'.',
				));
			}
			return $this->executeJob($jobID, 
				'Created new job #{$ID} {$Class} and executed SUCCESSFULLY', 
				'Created new job #{$ID} {$Class} but BROKE');
		}
		if ($existingCount == 1) {
			$recordSet = $list->toArray();
			if (count($recordSet) == 0 || count($recordSet) > 1) {
				return $this->echoTemplate(array(
					'Content' => 'Unexpected SQL data change during execution.'
				));
			}
			$job = reset($recordSet);
			return $this->executeJob($job->ID, 
					'Ran existing job #{$ID} {$Class} SUCCESSFULLY',
					'Ran existing job #{$ID} {$Class} but BROKE');
		}

		$jobs = array();
		foreach ($list->sort('ID') as $jobRecord) {
			$job = array(
				'Title' => "#$jobRecord->ID ".$jobRecord->getTitle(),
				'Segment' => $jobRecord->Implementation.'/'.$jobRecord->ID,
			);
			$job['Link'] = $this->Link($job['Segment']);
			$jobs[] = $job;
		}

		if (Director::is_cli()) {
			foreach($jobs as $job) {
				echo " * ".$job['Title']." : sake dev/jobs/" . $job['Segment'] . "\n";
			}
		}
		return $this->echoTemplate(array(
			'Records' => new ArrayList($jobs),
		), 'QueuedJobsDevAdmin_recordlist');
	}

	protected function executeJob($jobID, $successMessage, $brokeMessage) {
		ob_start();
		$isSuccessful = $this->jobQueue->runJob($jobID);
		$echoMessages = ob_get_contents();
		ob_end_clean();
		$jobRecord = QueuedJobDescriptor::get()->byID($jobID);
		$message = $successMessage;
		if (!$isSuccessful) {
			$message = $brokeMessage;
		}
		$message = str_replace('{$ID}', $jobRecord->ID, $message);
		$message = str_replace('{$Class}', $jobRecord->class, $message);
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

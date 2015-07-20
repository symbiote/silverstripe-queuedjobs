<?php
use AsyncPHP\Doorman\Rule\InMemoryRule;
use AsyncPHP\Doorman\Rule;

/**
 * Task used to process the job queue
 *
 * @author Marcus Nyeholt <marcus@silverstripe.com.au>
 * @license BSD http://silverstripe.org/bsd-license/
 */
class ProcessJobQueueTask extends BuildTask {

	/**
	 * @config
	 *
	 * @var string
	 */
	private static $engine = 'default';

	/**
	 *
	 * @var Rule[]
	 */
	protected $defaultRules = array();

	/**
	 * @return string
	 */
	public function getDescription() {
		return _t(
			'ProcessJobQueueTask.Description',
			'Used via a cron job to execute queued jobs that need to be run.'
		);
	}

	/**
	 * @param SS_HttpRequest $request
	 */
	public function run($request) {
		if($this->config()->engine === 'default') {
			$this->runWithDefaultEngine($request);
		} elseif($this->config()->engine === 'doorman') {
			$this->runWithDoormanEngine($request);
		} else {
			throw new InvalidArgumentException('ProcessJobQueueTask engine unrecognised');
		}
	}

	/**
	 * @param SS_HttpRequest $request
	 */
	protected function runWithDefaultEngine($request) {
		$service = $this->getService();

		$queue = $this->getQueue($request);

		if($request->getVar('list')) {
			for($i = 1; $i <= 3; $i++) {
				$jobs = $service->getJobList($i);
				$num = $jobs ? $jobs->Count() : 0;
				$this->writeLogLine('Found ' . $num . ' jobs for mode ' . $i . '.');
			}

			return;
		}

		$service->checkJobHealth();

		$nextJob = null;

		// see if we've got an explicit job ID, otherwise we'll just check the queue directly
		$job = $request->getVar('job');

		if($job && strpos($job, '-')) {
			$parts = explode('-', $job);

			$nextJob = DataObject::get_by_id('QueuedJobDescriptor', $parts[1]);
		} else {
			$nextJob = $service->getNextPendingJob($queue);
		}

		$this->logDescriptorStatus($nextJob, $queue);

		if($nextJob instanceof QueuedJobDescriptor) {
			$service->processJobQueue($queue);
		}
	}

	/**
	 * Returns an instance of the QueuedJobService.
	 *
	 * @return QueuedJobService
	 */
	protected function getService() {
		return singleton('QueuedJobService');
	}

	/**
	 * Resolves the queue name to one of a few aliases.
	 *
	 * @todo Solve the "Queued"/"queued" mystery!
	 *
	 * @param SS_HTTPRequest $request
	 *
	 * @return string
	 */
	protected function getQueue($request) {
		$queue = $request->getVar('queue');

		if(!$queue) {
			$queue = 'Queued';
		}

		switch(strtolower($queue)) {
			case 'immediate': {
				$queue = QueuedJob::IMMEDIATE;
				break;
			}
			case 'queued': {
				$queue = QueuedJob::QUEUED;
				break;
			}
			case 'large': {
				$queue = QueuedJob::LARGE;
				break;
			}
		}

		return $queue;
	}

	/**
	 * Write in a format expected by the output medium (CLI/HTML).
	 *
	 * @param string $line Line to be written out, without the newline character.
	 * @param null|string $prefix
	 */
	private function writeLogLine($line, $prefix = null) {
		if(!$prefix) {
			$prefix = '[' . date('Y-m-d H:i:s') . '] ';
		}

		if(Director::is_cli()) {
			echo $prefix . $line . "\n";
		} else {
			echo Convert::raw2xml($prefix . $line) . "<br>";
		}
	}

	/**
	 * Logs the status of the queued job descriptor.
	 *
	 * @param bool|null|QueuedJobDescriptor $descriptor
	 * @param string $queue
	 */
	protected function logDescriptorStatus($descriptor, $queue) {
		if(is_null($descriptor)) {
			$this->writeLogLine('No new jobs');
		}

		if($descriptor === false) {
			$this->writeLogLine('Job is still running on ' . $queue);
		}

		if($descriptor instanceof QueuedJobDescriptor) {
			$this->writeLogLine('Running ' . $descriptor->JobTitle . ' and others from ' . $queue . '.');
		}
	}

	/**
	 * @param SS_HttpRequest $request
	 */
	protected function runWithDoormanEngine($request) {
		// fix/prep any strange jobs!

		$service = $this->getService();
		$service->checkJobHealth();

		// split jobs out into multiple tasks...

		$manager = new DoormanProcessManager();
		// $manager->setLogPath(__DIR__);

		// Assign default rules
		$defaultRules = $this->getDefaultRules();
		if ($defaultRules) foreach($defaultRules as $rule) {
			$manager->addRule($rule);
		}

		$descriptor = $this->getNextJobDescriptorWithoutMutex($request);

		while($manager->tick() || $descriptor) {
			$this->logDescriptorStatus($descriptor, $this->getQueue($request));

			if($descriptor instanceof QueuedJobDescriptor) {
				$descriptor->JobStatus = QueuedJob::STATUS_INIT;
				$descriptor->write();

				$manager->addTask(new DoormanQueuedJobTask($descriptor));
			}

			sleep(1);

			$descriptor = $this->getNextJobDescriptorWithoutMutex($request);
		}
	}


	/**
	 * Assign default rules for this task
	 *
	 * @param Rule[] $rules
	 */
	public function setDefaultRules($rules) {
		$this->defaultRules = $rules;
	}

	/**
	 * @return Rule[] List of rules
	 */
	public function getDefaultRules() {
		return $this->defaultRules;
	}

	/**
	 * @param SS_HTTPRequest $request
	 *
	 * @return null|QueuedJobDescriptor
	 */
	protected function getNextJobDescriptorWithoutMutex($request) {
		$list = QueuedJobDescriptor::get()
			->filter('JobType', $this->getQueue($request))
			->sort('ID', 'ASC');

		$descriptor = $list
			->filter('JobStatus', QueuedJob::STATUS_WAIT)
			->first();

		if($descriptor) {
			return $descriptor;
		}

		return $list
			->filter('JobStatus', QueuedJob::STATUS_NEW)
			->where(sprintf(
				'"StartAfter" < \'%s\' OR "StartAfter" IS NULL',
				SS_DateTime::now()->getValue()
			))
			->first();
	}

	/**
	 * Fetches the next queued job descriptor to be processed, or false for mutex lock
	 * or null for no outstanding jobs.
	 *
	 * @param SS_HTTPRequest $request
	 * @param QueuedJobService $service
	 * @param string $queue
	 *
	 * @return null|bool|DataObject
	 */
	protected function getNextJobDescriptor($request, $service, $queue) {
		$job = $request->getVar('job');

		if($job && strpos($job, '-')) {
			$parts = explode('-', $job);

			return DataObject::get_by_id('QueuedJobDescriptor', $parts[1]);
		}

		return $service->getNextPendingJob($queue);
	}
}

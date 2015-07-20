<?php

/**
 * Description of BaseRunner
 *
 * @author dmooyman
 */
class BaseRunner {

	private static $dependencies = array(
		'Service' => '%$QueuedJobService'
	);
	
	/**
	 * @var QueuedJobService
	 */
	protected $service;

	/**
	 * Returns an instance of the QueuedJobService.
	 *
	 * @return QueuedJobService
	 */
	public function getService() {
		return $this->service;
	}

	/**
	 * @param QueuedJobService $service
	 */
	public function setService($service) {
		$this->service = $service;
	}

	/**
	 * Write in a format expected by the output medium (CLI/HTML).
	 *
	 * @param string $line Line to be written out, without the newline character.
	 * @param null|string $prefix
	 */
	protected function writeLogLine($line, $prefix = null) {
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
}

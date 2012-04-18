<?php

/**
 * A base implementation of a queued job that provides some convenience for implementations
 *
 * This implementation assumes that when you created your job class, you initialised the
 * jobData with relevant variables needed to process() your job later on in execution. If you do not,
 * please ensure you do before you queueJob() the job, to ensure the signature that is generated is 'correct'. 
 *
 * @author Marcus Nyeholt <marcus@silverstripe.com.au>
 * @license BSD http://silverstripe.org/bsd-license/
 */
abstract class AbstractQueuedJob implements QueuedJob {

	protected $jobData;
	protected $messages = array();
	protected $totalSteps = 0;
	protected $currentStep = 0;
	protected $isComplete = false;

	public function getTitle() {
		return "This needs a title!";
	}

	/**
	 * Sets a data object for persisting by adding its id and type to the serialised vars
	 *
	 * @param DataObject $object 
	 * @param string $name
	 * 				A name to give it, if you want to store more than one
	 */
	protected function setObject(DataObject $object, $name = 'Object') {
		$this->{$name . 'ID'} = $object->ID;
		$this->{$name . 'Type'} = $object->ClassName;
	}

	/**
	 * @param string $name 
	 */
	protected function getObject($name = 'Object') {
		$id = $this->{$name . 'ID'};
		$type = $this->{$name . 'Type'};
		if ($id) {
			return DataObject::get_by_id($type, $id);
		}
	}

	/**
	 * Return a signature for this queued job
	 *
	 * @return String
	 */
	public function getSignature() {
		return md5(get_class($this) . serialize($this->jobData));
	}

	/**
	 * Generate a somewhat random signature
	 *
	 * useful if you're want to make sure something is always added
	 */
	protected function randomSignature() {
		return md5(get_class($this) . time() . mt_rand(0, 100000));
	}

	/**
	 * By default jobs should just go into the default processing queue
	 *
	 * @return String
	 */
	public function getJobType() {
		return QueuedJob::QUEUED;
	}

	/**
	 * Implement yourself!
	 *
	 * Be aware that this is only executed ONCE for every job
	 *
	 * If you want to do some checking on every restart, look into using the prepareForRestart method
	 */
	public function setup() {

	}

	/**
	 * This is called when you want to perform some form of initialisation on a restart of a
	 * job. 
	 */
	public function prepareForRestart() {
		
	}

	/**
	 * Do some processing yourself!
	 */
	public function process() {
		
	}

	/**
	 * Method for determining whether the job is finished - you may override it if there's
	 * more to it than just this
	 */
	public function jobFinished() {
		return $this->isComplete;
	}

	/**
	 * Called when the job is determined to be 'complete'
	 */
	public function afterComplete() {
		
	}

	public function getJobData() {
		// okay, we NEED to store the subsite ID if there's one available
		if (!$this->SubsiteID && class_exists('Subsite')) {
			$this->SubsiteID = Subsite::currentSubsiteID();
		}

		$data = new stdClass();
		$data->totalSteps = $this->totalSteps;
		$data->currentStep = $this->currentStep;
		$data->isComplete = $this->isComplete;
		$data->jobData = $this->jobData;
		$data->messages = $this->messages;

		return $data;
	}

	public function setJobData($totalSteps, $currentStep, $isComplete, $jobData, $messages) {
		$this->totalSteps = $totalSteps;
		$this->currentStep = $currentStep;
		$this->isComplete = $isComplete;
		$this->jobData = $jobData;
		$this->messages = $messages;

		
	}

	public function addMessage($message, $severity='INFO') {
		$severity = strtoupper($severity);
		$this->messages[] = '[' . date('Y-m-d H:i:s') . "][$severity] $message";
	}

	/**
	 * Convenience methods for setting and getting job data
	 *
	 * @param mixed $name
	 * @param mixed $value 
	 */
	public function __set($name, $value) {
		if (!$this->jobData) {
			$this->jobData = new stdClass();
		}
		$this->jobData->$name = $value;
	}

	/**
	 * Retrieve some job data
	 *
	 * @param mixed $name
	 * @return mixed
	 */
	public function __get($name) {
		return isset($this->jobData->$name) ? $this->jobData->$name : null;
	}

}
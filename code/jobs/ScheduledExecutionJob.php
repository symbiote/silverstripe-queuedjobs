<?php

/**
 * A job that gets executed on a particular schedule. When it runs, 
 * it will call the onScheduledExecution method on the owning
 * dataobject. 
 *
 * @author marcus@silverstripe.com.au
 * @license BSD License http://silverstripe.org/bsd-license/
 */
class ScheduledExecutionJob extends AbstractQueuedJob {
	
	public function __construct($dataObject = null, $timesExecuted = 0) {
		if ($dataObject) {
			$this->objectID = $dataObject->ID;
			$this->objectType = $dataObject->ClassName;
			
			// captured so we have a unique hash generated for this job 
			$this->timesExecuted = $timesExecuted;
			$this->totalSteps = 1;
		}
	}
	
	public function getDataObject() {
		return DataObject::get_by_id($this->objectType, $this->objectID);
	}
	
	public function getTitle() {
		return 'Scheduled execution for '.$this->getDataObject()->getTitle();
	}
	

	public function setup() {
		
	}
	
	public function process() {
		$object = $this->getDataObject();
		if ($object) {
			$object->onScheduledExecution();
			
			// figure out what our rescheduled date should be
			$timeStr = $object->ExecuteFree;
			if ($object->ExecuteEvery) {
				$timeStr = '+1 ' . $object->ExecuteEvery;
			}
			
			$next = strtotime($timeStr);
			if ($next > time()) {
				// in the future
				$nextGen = date('Y-m-d H:i:s', strtotime($timeStr));
				$nextId = singleton('QueuedJobService')->queueJob(new ScheduledExecutionJob($object, $this->timesExecuted + 1), $nextGen);
				$object->ScheduledJobID = $nextId;
				$object->write();
			}
			
		}

		$this->currentStep++;
		$this->isComplete = true;
	}
}

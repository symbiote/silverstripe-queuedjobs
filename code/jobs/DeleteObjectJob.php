<?php

/**
 * A job used to delete a data object. Typically used for deletes that need to happen on 
 * a schedule, or where the delete may have some onflow affect that takes a while to 
 * finish the deletion. 
 *
 * @author marcus@silverstripe.com.au
 * @license BSD License http://silverstripe.org/bsd-license/
 */
class DeleteObjectJob extends AbstractQueuedJob {
	
	public function __construct($node = null) {
		if ($node) {
			$this->TargetClass = get_class($node);
			$this->TargetID = $node->ID;
			$this->currentStep = 0;
			$this->totalSteps = 1;
		}
	}
	
	public function getObject() {
		return DataObject::get_by_id($this->TargetClass, $this->TargetID);
	}
	
	public function getJobType() {
		return QueuedJob::IMMEDIATE;
	}
	
	/**
	 * @return string
	 */
	public function getTitle() {
		$obj = $this->getObject();
		if ($obj) {
			return _t('DeleteObjectJob.DELETE_OBJ', 'Delete ' . $obj->Title);
		} else {
			return _t('DeleteObjectJob.DELETE_JOB', 'Delete node');
		}
	}
	
	public function process() {
		$obj = $this->getObject();
		$obj->delete();
		$this->currentStep = 1;
		$this->isComplete = true;
	}
}

<?php

/**
 * An extension that can be added to objects that automatically
 * adds scheduled execution capabilities to data objects. 
 * 
 * Developers who want to use these capabilities can set up 
 *
 * @author marcus@silverstripe.com.au
 * @license BSD License http://silverstripe.org/bsd-license/
 */
class ScheduledExecutionExtension extends DataExtension {
	
	public static $db = array(
		'FirstExecution'		=> 'SS_Datetime',
		'ExecuteEvery'			=> "Enum(',Hour,Day,Week,Fortnight,Month,Year')",
		'ExecuteFree'			=> 'Varchar',
	);
	
	public static $has_one = array(
		'ScheduledJob'			=> 'QueuedJobDescriptor',
	);
	
	/**
	 *
	 * @param FieldSet $fields 
	 */
	public function updateCMSFields($fields) {
		$fields->addFieldsToTab('Root.Schedule', array(
			$dt = new Datetimefield('FirstExecution', _t('ScheduledExecution.FIRST_EXECUTION', 'First Execution')),
			new DropdownField('ExecuteEvery', _t('ScheduledExecution.EXECUTE_EVERY', 'Execute every'), $this->owner->dbObject('ExecuteEvery')->enumValues()),
			new TextField('ExecuteFree', _t('ScheduledExecution.EXECUTE_FREE','Scheduled (in strtotime format from first execution)'))
		));

		if ($this->owner->ScheduledJobID) {
			$jobTime = $this->owner->ScheduledJob()->StartAfter;
			$fields->addFieldsToTab('Root.Schedule', array(
				new ReadonlyField('NextRunDate', _t('ScheduledExecution.NEXT_RUN_DATE', 'Next run date'), $jobTime)
			));
		}

		$dt->getDateField()->setConfig('showcalendar', true);
		$dt->getTimeField()->setConfig('showdropdown', true);
	}

	public function onBeforeWrite() {
		parent::onBeforeWrite();
		
		if ($this->owner->FirstExecution) {
			$changed = $this->owner->getChangedFields();
			$changed = isset($changed['FirstExecution']) || isset($changed['ExecuteEvery']) || isset($changed['ExecuteFree']);

			if ($changed && $this->owner->ScheduledJobID) {
				if ($this->owner->ScheduledJob()->exists()) {
					$this->owner->ScheduledJob()->delete();
				}
				
				$this->owner->ScheduledJobID = 0;
			}

			if (!$this->owner->ScheduledJobID) {
				$job = new ScheduledExecutionJob($this->owner);
				$time = date('Y-m-d H:i:s');
				if ($this->owner->FirstExecution) {
					$time = date('Y-m-d H:i:s', strtotime($this->owner->FirstExecution));
				}

				$this->owner->ScheduledJobID = singleton('QueuedJobService')->queueJob($job, $time);
			}
		}
	}


	/**
	 * Define your own version of this method in your data objects to be executed EVERY time 
	 * the scheduled job triggers. 
	 */
	public function onScheduledExecution() {
		
	}
}

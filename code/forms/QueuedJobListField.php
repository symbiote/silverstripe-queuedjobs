<?php

/*
 * @license BSD http://silverstripe.org/bsd-license/
 */
class QueuedJobListField extends TableListField {
	function __construct($name, $sourceClass, $fieldList = null, $sourceFilter = null,
		$sourceSort = null, $sourceJoin = null) {
		parent::__construct($name, $sourceClass, $fieldList, $sourceFilter, $sourceSort, $sourceJoin);
		$this->itemClass = 'QueuedJobListField_Item';
	}

	function handleItem($request) {
		return new QueuedJobListField_ItemRequest($this, $request->param('ID'));
	}

	function FieldHolder() {
		Requirements::javascript('queuedjobs/javascript/QueuedJobListField.js');
		return parent::FieldHolder();
	}
}

/**
 * @author Marcus Nyeholt <marcus@silverstripe.com.au>
 */
class QueuedJobListField_Item extends TableListField_Item
{
	function PauseLink() {
		return Controller::join_links($this->Link(), "pause");
	}

	function ResumeLink() {
		return Controller::join_links($this->Link(), "resume");
	}
	
	function ExecuteLink() {
		return Controller::join_links($this->Link(), 'execute');
	}

	/**
	 * Returns all row-based actions not disallowed through permissions.
	 * See TableListField->Action for a similiar dummy-function to work
	 * around template-inheritance issues.
	 *
	 * @return DataObjectSet
	 */
	function Actions() {
		$allowedActions = new DataObjectSet();
		foreach($this->parent->actions as $actionName => $actionSettings) {
			$can = true;
			switch ($actionName) {
				case 'pause': {
					if ($this->item->JobStatus != QueuedJob::STATUS_RUN && $this->item->JobStatus != QueuedJob::STATUS_WAIT) {
						$can = false;
					}
					break;
				}
				case 'resume': {
					if ($this->item->JobStatus != QueuedJob::STATUS_PAUSED && $this->item->JobStatus != QueuedJob::STATUS_BROKEN) {
						$can = false;
					}
					break;
				}
				case 'delete': {
					if (!($this->item->JobStatus == QueuedJob::STATUS_NEW || 
							$this->item->JobStatus == QueuedJob::STATUS_BROKEN || 
							$this->item->JobStatus == QueuedJob::STATUS_PAUSED ||
							$this->item->JobStatus == QueuedJob::STATUS_INIT
							)) {
						$can = false;
					}
					break;
				}
				case 'execute': {
					if (!($this->item->JobStatus == QueuedJob::STATUS_NEW || $this->item->JobStatus == QueuedJob::STATUS_WAIT || $this->item->JobStatus == QueuedJob::STATUS_PAUSED)) {
						$can = false;
					}
					if ($this->item->RunAsID != Member::currentUserID() && !Permission::check('ADMIN')) {
						$can = false;
					}
					break;
				}
			}

			if($can && $this->parent->Can($actionName)) {
				$allowedActions->push(new ArrayData(array(
					'Name' => $actionName,
					'Link' => $this->{ucfirst($actionName).'Link'}(),
					'Icon' => $actionSettings['icon'],
					'IconDisabled' => $actionSettings['icon_disabled'],
					'Label' => $actionSettings['label'],
					'Class' => $actionSettings['class'],
					'Default' => ($actionName == $this->parent->defaultAction),
					'IsAllowed' => $this->Can($actionName),
				)));
			}
		}

		return $allowedActions;
	}
}

class QueuedJobListField_ItemRequest extends TableListField_ItemRequest {
	public function pause() {
		$this->dataObj()->pause();
	}

	public function resume() {
		$this->dataObj()->resume();
	}
	
	/**
	 * Immediately executes the given job
	 */
	public function execute() {
		$this->dataObj()->execute();
	}
}
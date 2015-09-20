<?php
/**
 * This class is a {@link GridField} component that adds a delete action for objects.
 *
 * This component also supports unlinking a relation instead of deleting the object.
 * Use the {@link $removeRelation} property set in the constructor.
 *
 * <code>
 * $action = new GridFieldDeleteAction(); // delete objects permanently
 * $action = new GridFieldDeleteAction(true); // removes the relation to object, instead of deleting
 * </code>
 *
 * @package queuedjobs
 * @subpackage forms
 */
class GridFieldQueuedJobExecute implements GridField_ColumnProvider, GridField_ActionProvider {

	protected $action = 'execute';

	/**
	 * @var array
	 */
	protected $icons = array(
		'execute'		=> 'navigation',
		'pause'			=> 'minus-circle_disabled',
		'resume'		=> 'arrow-circle-double',
	);

	/**
	 * Call back to see if the record's action icon should be shown.
	 *
	 * @var closure
	 */
	protected $viewCheck;

	public function __construct($action = 'execute', $check = null) {
		$this->action = $action;
		if (!$check) {
			$check = function ($record) {
				return $record->JobStatus == QueuedJob::STATUS_WAIT || $record->JobStatus == QueuedJob::STATUS_NEW;
			};
		}

		$this->viewCheck = $check;
	}

	/**
	 * Add a column 'Delete'
	 *
	 * @param type $gridField
	 * @param array $columns
	 */
	public function augmentColumns($gridField, &$columns) {
		if(!in_array('Actions', $columns)) {
			$columns[] = 'Actions';
		}
	}

	/**
	 * Return any special attributes that will be used for FormField::createTag()
	 *
	 * @param GridField $gridField
	 * @param DataObject $record
	 * @param string $columnName
	 * @return array
	 */
	public function getColumnAttributes($gridField, $record, $columnName) {
		return array('class' => 'col-buttons');
	}

	/**
	 * Add the title
	 *
	 * @param GridField $gridField
	 * @param string $columnName
	 * @return array
	 */
	public function getColumnMetadata($gridField, $columnName) {
		if($columnName == 'Actions') {
			return array('title' => '');
		}
	}

	/**
	 * Which columns are handled by this component
	 *
	 * @param type $gridField
	 * @return type
	 */
	public function getColumnsHandled($gridField) {
		return array('Actions');
	}

	/**
	 * Which GridField actions are this component handling
	 *
	 * @param GridField $gridField
	 * @return array
	 */
	public function getActions($gridField) {
		return array('execute', 'pause', 'resume');
	}

	/**
	 * @param GridField $gridField
	 * @param DataObject $record
	 * @param string $columnName
	 * @return string - the HTML for the column
	 */
	public function getColumnContent($gridField, $record, $columnName) {
		$icon = $this->icons[$this->action];

		if ($this->viewCheck) {
			$func = $this->viewCheck;
			if (!$func($record)) {
				return;
			}
		}

		$field = GridField_FormAction::create(
			$gridField,
			'ExecuteJob' . $record->ID,
			false,
			$this->action,
			array('RecordID' => $record->ID)
		);
		$field->addExtraClass('gridfield-button-job' . $this->action)
			->setAttribute('title', ucfirst($this->action))
			->setAttribute('data-icon', $icon);
		return $field->Field();
	}

	/**
	 * Handle the actions and apply any changes to the GridField
	 *
	 * @param GridField $gridField
	 * @param string $actionName
	 * @param mixed $arguments
	 * @param array $data - form data
	 * @return void
	 */
	public function handleAction(GridField $gridField, $actionName, $arguments, $data) {
		$actions = $this->getActions(null);
		if (in_array($actionName, $actions)) {
			$item = $gridField->getList()->byID($arguments['RecordID']);
			if(!$item) {
				return;
			}
			$item->$actionName();
			Requirements::clear();
		}
	}
}

<?php

namespace Symbiote\QueuedJobs\Forms;

use SilverStripe\Forms\GridField\AbstractGridFieldComponent;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridField_ActionProvider;
use SilverStripe\Forms\GridField\GridField_ColumnProvider;
use SilverStripe\Forms\GridField\GridField_FormAction;
use SilverStripe\ORM\DataObject;
use SilverStripe\View\Requirements;
use Symbiote\QueuedJobs\Services\QueuedJob;

class GridFieldQueuedJobExecute extends AbstractGridFieldComponent implements
    GridField_ColumnProvider,
    GridField_ActionProvider
{
    protected $action = 'execute';

    /**
     * CSS icon class names for each action (see silverstripe-admin fonts)
     *
     * @var array
     */
    protected $icons = [
        'execute' => 'font-icon-block-media',
        'pause'   => 'font-icon-cancel-circled',
        'resume'  => 'font-icon-sync',
    ];

    /**
     * Call back to see if the record's action icon should be shown.
     *
     * @var callable
     */
    protected $viewCheck;

    /**
     * @param string   $action
     * @param callable $check
     */
    public function __construct($action = 'execute', $check = null)
    {
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
     * @param GridField $gridField
     * @param array     $columns
     */
    public function augmentColumns($gridField, &$columns)
    {
        if (!in_array('Actions', $columns)) {
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
    public function getColumnAttributes($gridField, $record, $columnName)
    {
        return array('class' => 'grid-field__col-compact');
    }

    /**
     * Add the title
     *
     * @param GridField $gridField
     * @param string $columnName
     * @return array
     */
    public function getColumnMetadata($gridField, $columnName)
    {
        if ($columnName == 'Actions') {
            return array('title' => '');
        }
    }

    /**
     * Which columns are handled by this component
     *
     * @param GridField $gridField
     * @return array
     */
    public function getColumnsHandled($gridField)
    {
        return array('Actions');
    }

    /**
     * Which GridField actions are this component handling
     *
     * @param GridField $gridField
     * @return array
     */
    public function getActions($gridField)
    {
        return array('execute', 'pause', 'resume');
    }

    /**
     * @param GridField $gridField
     * @param DataObject $record
     * @param string $columnName
     * @return string|void - the HTML for the column
     */
    public function getColumnContent($gridField, $record, $columnName)
    {
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

        $humanTitle = ucfirst($this->action);
        $title = _t(__CLASS__ . '.' . $humanTitle, $humanTitle);

        $field
            ->addExtraClass('gridfield-button-job' . $this->action)
            ->addExtraClass($icon)
            ->addExtraClass('btn--icon-md btn--no-text grid-field__icon-action')
            ->setAttribute('title', $title)
            ->setDescription($title);

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
    public function handleAction(GridField $gridField, $actionName, $arguments, $data)
    {
        $actions = $this->getActions(null);
        if (in_array($actionName, $actions)) {
            $item = $gridField->getList()->byID($arguments['RecordID']);
            if (!$item) {
                return;
            }
            $item->$actionName();
            Requirements::clear();
        }
    }
}

<?php

namespace SilverStripe\QueuedJobs\Controllers;

use ReflectionClass;
use SilverStripe\Admin\ModelAdmin;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Forms\DatetimeField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordEditor;
use SilverStripe\MultiValueField\Forms\MultiValueTextField;
use SilverStripe\ORM\DataList;
use SilverStripe\QueuedJobs\Forms\GridFieldQueuedJobExecute;
use SilverStripe\QueuedJobs\Services\QueuedJob;
use SilverStripe\Security\Permission;

/**
 * @author Marcus Nyeholt <marcus@silverstripe.com.au>
 * @license BSD http://silverstripe.org/bsd-license/
 */
class QueuedJobsAdmin extends ModelAdmin
{
    /**
     * @var string
     */
    private static $url_segment = 'queuedjobs';

    /**
     * @var string
     */
    private static $menu_title = 'Jobs';

    /**
     * @var string
     */
    private static $menu_icon = "queuedjobs/images/clipboard.png";

    /**
     * @var array
     */
    private static $managed_models = array('SilverStripe\\QueuedJobs\\DataObjects\\QueuedJobDescriptor');

    /**
     * @var array
     */
    private static $dependencies = array(
        'jobQueue' => '%$SilverStripe\\QueuedJobs\\Services\\QueuedJobService',
    );

    /**
     * @var array
     */
    private static $allowed_actions = array(
        'EditForm'
    );

    /**
     * @var QueuedJobService
     */
    public $jobQueue;

    /**
     * @param int $id
     * @param FieldList $fields
     * @return Form
     */
    public function getEditForm($id = null, $fields = null)
    {
        $form = parent::getEditForm($id, $fields);

        $filter = $this->jobQueue->getJobListFilter(null, 300);

        $list = DataList::create('SilverStripe\\QueuedJobs\\DataObjects\\QueuedJobDescriptor');
        $list = $list->where($filter)->sort('Created', 'DESC');

        $gridFieldConfig = GridFieldConfig_RecordEditor::create()
            ->addComponent(new GridFieldQueuedJobExecute('execute'))
            ->addComponent(new GridFieldQueuedJobExecute('pause', function ($record) {
                return $record->JobStatus == QueuedJob::STATUS_WAIT || $record->JobStatus == QueuedJob::STATUS_RUN;
            }))
            ->addComponent(new GridFieldQueuedJobExecute('resume', function ($record) {
                return $record->JobStatus == QueuedJob::STATUS_PAUSED || $record->JobStatus == QueuedJob::STATUS_BROKEN;
            }))
            ->removeComponentsByType('SilverStripe\\Forms\\GridField\\GridFieldAddNewButton');


        // Set messages to HTML display format
        $formatting = array(
            'Messages' => function ($val, $obj) {
                return "<div style='max-width: 300px; max-height: 200px; overflow: auto;'>$obj->Messages</div>";
            },
        );
        $gridFieldConfig->getComponentByType('SilverStripe\\Forms\\GridField\\GridFieldDataColumns')
            ->setFieldFormatting($formatting);

        // Replace gridfield
        $grid = new GridField(
            'SilverStripe\\QueuedJobs\\DataObjects\\QueuedJobDescriptor',
            _t('QueuedJobs.JobsFieldTitle', 'Jobs'),
            $list,
            $gridFieldConfig
        );
        $grid->setForm($form);
        $form->Fields()->replaceField('SilverStripe\\QueuedJobs\\DataObjects\\QueuedJobDescriptor', $grid);

        if (Permission::check('ADMIN')) {
            $types = ClassInfo::subclassesFor('SilverStripe\\QueuedJobs\\Services\\AbstractQueuedJob');
            $types = array_combine($types, $types);
            unset($types['SilverStripe\\QueuedJobs\\Services\\AbstractQueuedJob']);
            $jobType = DropdownField::create('JobType', _t('QueuedJobs.CREATE_JOB_TYPE', 'Create job of type'), $types);
            $jobType->setEmptyString('(select job to create)');
            $form->Fields()->push($jobType);

            $jobParams = MultiValueTextField::create(
                'JobParams',
                _t('QueuedJobs.JOB_TYPE_PARAMS', 'Constructor parameters for job creation')
            );
            $form->Fields()->push($jobParams);

            $form->Fields()->push(
                $dt = DatetimeField::create('JobStart', _t('QueuedJobs.START_JOB_TIME', 'Start job at'))
            );
            $dt->getDateField()->setConfig('showcalendar', true);

            $actions = $form->Actions();
            $actions->push(FormAction::create('createjob', _t('QueuedJobs.CREATE_NEW_JOB', 'Create new job')));
        }

        $this->extend('updateEditForm', $form);

        return $form;
    }

    /**
     * @return string
     */
    public function Tools()
    {
        return '';
    }

    /**
     * @param array $data
     * @param Form $form
     * @return SS_HTTPResponse
     */
    public function createjob($data, Form $form)
    {
        if (Permission::check('ADMIN')) {
            $jobType = isset($data['JobType']) ? $data['JobType'] : '';
            $params = isset($data['JobParams']) ? $data['JobParams'] : array();
            $time = isset($data['JobStart']) ? $data['JobStart'] : null;

            $js = $form->Fields()->dataFieldByName('JobStart');
            $time = $js->Value();

            if ($jobType && class_exists($jobType)) {
                $jobClass = new ReflectionClass($jobType);
                $job = $jobClass->newInstanceArgs($params);
                $this->jobQueue->queueJob($job, $time);
            }
        }
        return $this->responseNegotiator->respond($this->getRequest());
    }
}

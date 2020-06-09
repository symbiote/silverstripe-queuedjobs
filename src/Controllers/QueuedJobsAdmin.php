<?php

namespace Symbiote\QueuedJobs\Controllers;

use ReflectionClass;
use SilverStripe\Admin\ModelAdmin;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Forms\DatetimeField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldAddNewButton;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordEditor;
use SilverStripe\Forms\GridField\GridFieldDataColumns;
use SilverStripe\Forms\GridField\GridFieldFilterHeader;
use SilverStripe\Forms\GridField\GridFieldPageCount;
use SilverStripe\Forms\GridField\GridFieldToolbarHeader;
use SilverStripe\Forms\TextareaField;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\Security\Permission;
use SilverStripe\Security\Security;
use Symbiote\QueuedJobs\DataObjects\QueuedJobDescriptor;
use Symbiote\QueuedJobs\Forms\GridFieldQueuedJobExecute;
use Symbiote\QueuedJobs\Services\AbstractQueuedJob;
use Symbiote\QueuedJobs\Services\QueuedJob;
use Symbiote\QueuedJobs\Services\QueuedJobService;
use Terraformers\RichFilterHeader\Form\GridField\RichFilterHeader;

/**
 * @author Marcus Nyeholt <marcus@symbiote.com.au>
 * @license BSD http://silverstripe.org/bsd-license/
 */
class QueuedJobsAdmin extends ModelAdmin
{
    const SCHEDULED_FILTER_FUTURE = 'future';
    const SCHEDULED_FILTER_PAST = 'past';

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
    private static $menu_icon_class = 'font-icon-checklist';

    /**
     * @var array
     */
    private static $managed_models = [
        QueuedJobDescriptor::class
    ];

    /**
     * @var array
     */
    private static $dependencies = [
        'jobQueue' => '%$' . QueuedJobService::class,
    ];

    /**
     * @var array
     */
    private static $allowed_actions = [
        'EditForm'
    ];

    /**
     * European date format
     * @var string
     */
    private static $date_format_european = 'dd/MM/yyyy';

    /**
     * Enables advanced admin UI
     * This requires the optional module
     * silverstripe-terraformers/gridfield-rich-filter-header
     *
     * @config
     * @var bool
     */
    private static $advanced_admin_ui = false;

    /**
     * @var QueuedJobService
     */
    public $jobQueue;

    /**
     * @config The number of seconds to include jobs that have finished
     * default: 7200 (2 hours), examples: 3600(1h), 86400(1d)
     */
    private static $max_finished_jobs_age = 7200;

    /**
     * @param int $id
     * @param FieldList $fields
     * @return Form
     */
    public function getEditForm($id = null, $fields = null)
    {
        $form = parent::getEditForm($id, $fields);

        $filter = $this->jobQueue->getJobListFilter(null, self::config()->max_finished_jobs_age);

        $list = QueuedJobDescriptor::get()->where($filter)->sort('Created', 'DESC');

        $gridFieldConfig = GridFieldConfig_RecordEditor::create()
            ->addComponent(new GridFieldQueuedJobExecute('execute'))
            ->addComponent(new GridFieldQueuedJobExecute('pause', function ($record) {
                return $record->JobStatus == QueuedJob::STATUS_WAIT || $record->JobStatus == QueuedJob::STATUS_RUN;
            }))
            ->addComponent(new GridFieldQueuedJobExecute('resume', function ($record) {
                return $record->JobStatus == QueuedJob::STATUS_PAUSED || $record->JobStatus == QueuedJob::STATUS_BROKEN;
            }))
            ->removeComponentsByType([
                GridFieldAddNewButton::class,
                GridFieldPageCount::class,
                GridFieldToolbarHeader::class,
            ]);

        // Set messages to HTML display format
        $formatting = array(
            'Messages' => function ($val, $obj) {
                return "<div style='max-width: 300px; max-height: 200px; overflow: auto;'>$obj->Messages</div>";
            },
        );
        $gridFieldConfig->getComponentByType(GridFieldDataColumns::class)
            ->setFieldFormatting($formatting);

        // Replace gridfield
        /** @skipUpgrade */
        $grid = GridField::create(
            'QueuedJobDescriptor',
            '',
            $list,
            $gridFieldConfig
        );
        $grid->setForm($form);
        /** @skipUpgrade */
        $form->Fields()->replaceField($this->sanitiseClassName(QueuedJobDescriptor::class), $grid);

        if (QueuedJobDescriptor::singleton()->canCreate()) {
            $types = ClassInfo::subclassesFor(AbstractQueuedJob::class);
            $types = array_combine($types, $types);
            foreach ($types as $class) {
                $reflection = new ReflectionClass($class);
                if (!$reflection->isInstantiable()) {
                    unset($types[$class]);
                }
            }
            $jobType = DropdownField::create(
                'JobType',
                _t(__CLASS__ . '.CREATE_JOB_TYPE', 'Create job of type'),
                $types
            );
            $jobType->setEmptyString('(select job to create)');
            $form->Fields()->push($jobType);

            $jobParams = TextareaField::create(
                'JobParams',
                _t(__CLASS__ . '.JOB_TYPE_PARAMS', 'Constructor parameters for job creation (one per line)')
            );
            $form->Fields()->push($jobParams);

            $form->Fields()->push(
                $dt = DatetimeField::create('JobStart', _t(__CLASS__ . '.START_JOB_TIME', 'Start job at'))
            );

            $actions = $form->Actions();
            $actions->push(
                FormAction::create('createjob', _t(__CLASS__ . '.CREATE_NEW_JOB', 'Create new job'))
                    ->addExtraClass('btn btn-primary')
            );
        }

        if ($this->config()->get('advanced_admin_ui')) {
            $this->enableAdvancedAdminUi($grid);
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
     * @param  array $data
     * @param  Form $form
     * @return HTTPResponse
     */
    public function createjob($data, Form $form)
    {
        if (QueuedJobDescriptor::singleton()->canCreate()) {
            $jobType = isset($data['JobType']) ? $data['JobType'] : '';
            $params = isset($data['JobParams']) ? preg_split('/\R/', trim($data['JobParams'])) : [];

            if (isset($data['JobStart'])) {
                $time = is_array($data['JobStart']) ? implode(' ', $data['JobStart']) : $data['JobStart'];
            } else {
                $time = null;
            }

            // If the user has select the European date format as their setting then replace '/' with '-' in the
            // date string so PHP treats the date as this format.
            if (Security::getCurrentUser()->DateFormat == self::$date_format_european) {
                $time = str_replace('/', '-', $time);
            }

            if ($jobType && class_exists($jobType) && is_subclass_of($jobType, QueuedJob::class)) {
                $jobClass = new ReflectionClass($jobType);
                $job = $jobClass->newInstanceArgs($params);
                if ($this->jobQueue->queueJob($job, $time)) {
                    $form->sessionMessage(_t(__CLASS__ . '.QueuedJobSuccess', 'Successfully queued job'), 'success');
                }
            }
        }
        return $this->redirectBack();
    }

    /**
     * @param GridField $gridField
     */
    private function enableAdvancedAdminUi(GridField $gridField)
    {
        if (!class_exists(RichFilterHeader::class)) {
            return;
        }

        $config = $gridField->getConfig();

        /** @var GridFieldDataColumns $gridFieldColumns */
        $gridFieldColumns = $config->getComponentByType(GridFieldDataColumns::class);

        $gridFieldColumns->setDisplayFields([
            'getTrimmedImplementation' => _t(__CLASS__ . '.SUMMARY_TYPE', 'Type'),
            'JobTypeString' => _t(__CLASS__ . '.SUMMARY_QUEUE', 'Queue'),
            'JobStatus' => _t(__CLASS__ . '.SUMMARY_STATUS', 'Status'),
            'JobTitle' => _t(__CLASS__ . '.SUMMARY_DESCRIPTION', 'Description'),
            'Created' => _t(__CLASS__ . '.SUMMARY_ADDED', 'Added'),
            'StartAfter' => _t(__CLASS__ . '.SUMMARY_SCHEDULED', 'Scheduled'),
            'JobFinished' => _t(__CLASS__ . '.SUMMARY_FINISHED', 'Finished'),
        ]);

        $config->removeComponentsByType(GridFieldFilterHeader::class);

        $filter = new RichFilterHeader();
        $filter
            ->setFilterConfig([
                'getTrimmedImplementation' => 'Implementation',
                'Description' => 'JobTitle',
                'Status' => [
                    'title' => 'JobStatus',
                    'filter' => 'ExactMatchFilter',
                ],
                'JobTypeString' => [
                    'title' => 'JobType',
                    'filter' => 'ExactMatchFilter',
                ],
                'Created' => 'Added',
                'StartAfter' => 'Scheduled',
            ])
            ->setFilterFields([
                'JobType' => $queueType = DropdownField::create(
                    '',
                    '',
                    $this->getQueueTypes()
                ),
                'JobStatus' => $jobStatus = DropdownField::create(
                    '',
                    '',
                    $this->getJobStatuses()
                ),
                'Added' => $added = DropdownField::create(
                    '',
                    '',
                    $this->getAddedDates()
                ),
                'Scheduled' => $scheduled = DropdownField::create(
                    '',
                    '',
                    [
                        self::SCHEDULED_FILTER_FUTURE => self::SCHEDULED_FILTER_FUTURE,
                        self::SCHEDULED_FILTER_PAST => self::SCHEDULED_FILTER_PAST,
                    ]
                ),
            ])
            ->setFilterMethods([
                'Added' => static function (DataList $list, $name, $value) {
                    if ($value) {
                        $added = DBDatetime::now()->modify($value);

                        return $list->filter(['Created:LessThanOrEqual' => $added->Rfc2822()]);
                    }

                    return $list;
                },
                'Scheduled' => static function (DataList $list, $name, $value) {
                    if ($value === static::SCHEDULED_FILTER_FUTURE) {
                        return $list->filter([
                            'StartAfter:GreaterThan' => DBDatetime::now()->Rfc2822(),
                        ]);
                    }

                    if ($value === static::SCHEDULED_FILTER_PAST) {
                        return $list->filter([
                            'StartAfter:LessThanOrEqual' => DBDatetime::now()->Rfc2822(),
                        ]);
                    }

                    return $list;
                },
            ]);

        foreach ([$jobStatus, $queueType, $added, $scheduled] as $dropDownField) {
            /** @var DropdownField $dropDownField */
            $dropDownField->setEmptyString('-- select --');
        }

        $config->addComponent($filter, GridFieldPaginator::class);
    }

    /**
     * Queue types options for drop down field
     *
     * @return array
     */
    private function getQueueTypes()
    {
        /** @var QueuedJobDescriptor $job */
        $job = QueuedJobDescriptor::singleton();
        $map = $job->getJobTypeValues();
        $values = array_values($map);
        $keys = [];

        foreach (array_keys($map) as $key) {
            $keys[] = (int) $key;
        }

        return array_combine($keys, $values);
    }

    /**
     * All possible job statuses (this list is not exposed by the module)
     * intended to be used in a drop down field
     *
     * @return array
     */
    private function getJobStatuses()
    {
        /** @var QueuedJobDescriptor $job */
        $job = QueuedJobDescriptor::singleton();
        $statuses = $job->getJobStatusValues();

        sort($statuses, SORT_STRING);

        $statuses = array_combine($statuses, $statuses);

        return $statuses;
    }

    /**
     * Date options for added dates drop down field
     *
     * @return array
     */
    private function getAddedDates()
    {
        return [
            '-1 day' => '1 day or older',
            '-3 day' => '3 days or older',
            '-7 day' => '7 days or older',
            '-14 day' => '14 days or older',
            '-1 month' => '1 month or older',
        ];
    }
}

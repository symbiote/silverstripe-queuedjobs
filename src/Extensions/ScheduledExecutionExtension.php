<?php

namespace Symbiote\QueuedJobs\Extensions;

use SilverStripe\Forms\DatetimeField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldGroup;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\NumericField;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\FieldType\DBDatetime;
use Symbiote\QueuedJobs\DataObjects\QueuedJobDescriptor;
use Symbiote\QueuedJobs\Jobs\ScheduledExecutionJob;
use Symbiote\QueuedJobs\Services\QueuedJobService;

/**
 * An extension that can be added to objects that automatically
 * adds scheduled execution capabilities to data objects.
 *
 * Developers who want to use these capabilities can set up
 *
 * @author marcus@symbiote.com.au
 * @license BSD License http://silverstripe.org/bsd-license/
 */
class ScheduledExecutionExtension extends DataExtension
{
    /**
     * @var array
     */
    private static $db = array(
        'FirstExecution' => 'DBDatetime',
        'ExecuteInterval' => 'Int',
        'ExecuteEvery' => "Enum(',Minute,Hour,Day,Week,Fortnight,Month,Year')",
        'ExecuteFree' => 'Varchar',
    );

    /**
     * @var array
     */
    private static $defaults = array(
        'ExecuteInterval' => 1,
    );

    /**
     * @var array
     */
    private static $has_one = array(
        'ScheduledJob' => QueuedJobDescriptor::class,
    );

    /**
     * @param FieldList $fields
     */
    public function updateCMSFields(FieldList $fields)
    {
        $fields->removeByName([
            'ExecuteInterval',
            'ExecuteEvery',
            'ExecuteFree',
            'FirstExecution'
        ]);

        $fields->findOrMakeTab(
            'Root.Schedule',
            _t(__CLASS__ . '.ScheduleTabTitle', 'Schedule')
        );

        $fields->addFieldsToTab('Root.Schedule', array(
            $dt = DatetimeField::create('FirstExecution', _t(__CLASS__ . '.FIRST_EXECUTION', 'First Execution')),
            FieldGroup::create(
                NumericField::create('ExecuteInterval', ''),
                DropdownField::create(
                    'ExecuteEvery',
                    '',
                    array(
                        '' => '',
                        'Minute' => _t(__CLASS__ . '.ExecuteEveryMinute', 'Minute'),
                        'Hour' => _t(__CLASS__ . '.ExecuteEveryHour', 'Hour'),
                        'Day' => _t(__CLASS__ . '.ExecuteEveryDay', 'Day'),
                        'Week' => _t(__CLASS__ . '.ExecuteEveryWeek', 'Week'),
                        'Fortnight' => _t(__CLASS__ . '.ExecuteEveryFortnight', 'Fortnight'),
                        'Month' => _t(__CLASS__ . '.ExecuteEveryMonth', 'Month'),
                        'Year' => _t(__CLASS__ . '.ExecuteEveryYear', 'Year'),
                    )
                )
            )->setTitle(_t(__CLASS__ . '.EXECUTE_EVERY', 'Execute every')),
            TextField::create(
                'ExecuteFree',
                _t(__CLASS__ . '.EXECUTE_FREE', 'Scheduled (in strtotime format from first execution)')
            )
        ));

        if ($this->owner->ScheduledJobID) {
            $jobTime = $this->owner->ScheduledJob()->StartAfter;
            $fields->addFieldsToTab('Root.Schedule', array(
                ReadonlyField::create('NextRunDate', _t(__CLASS__ . '.NEXT_RUN_DATE', 'Next run date'), $jobTime)
            ));
        }
    }

    public function onBeforeWrite()
    {
        parent::onBeforeWrite();

        if ($this->owner->FirstExecution) {
            $changed = $this->owner->getChangedFields();
            $changed = (
                isset($changed['FirstExecution'])
                || isset($changed['ExecuteInterval'])
                || isset($changed['ExecuteEvery'])
                || isset($changed['ExecuteFree'])
            );

            if ($changed && $this->owner->ScheduledJobID) {
                if ($this->owner->ScheduledJob()->exists()) {
                    $this->owner->ScheduledJob()->delete();
                }

                $this->owner->ScheduledJobID = 0;
            }

            if (!$this->owner->ScheduledJobID) {
                $job = new ScheduledExecutionJob($this->owner);
                $time = DBDatetime::now()->Rfc2822();
                if ($this->owner->FirstExecution) {
                    $time = DBDatetime::create()->setValue($this->owner->FirstExecution)->Rfc2822();
                }

                $this->owner->ScheduledJobID = QueuedJobService::singleton()
                    ->queueJob($job, $time);
            }
        }
    }

    /**
     * Define your own version of this method in your data objects to be executed EVERY time
     * the scheduled job triggers.
     */
    public function onScheduledExecution()
    {
    }
}

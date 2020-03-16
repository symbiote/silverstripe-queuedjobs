<?php

namespace Symbiote\QueuedJobs\DataObjects;

use DateInterval;
use DateTime;
use SilverStripe\Assets\Filesystem;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Convert;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\DatetimeField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\HeaderField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\NumericField;
use SilverStripe\Forms\TextareaField;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
use Symbiote\QueuedJobs\Services\QueuedJob;
use Symbiote\QueuedJobs\Services\QueuedJobService;

/**
 * A QueuedJobDescriptor is the stored representation of a piece of work that could take a while to execute,
 * because of which it is desirable to not have it executing in parallel to other jobs.
 *
 * A queued job should always attempt to report how many potential dataobjects will be affected by being executed;
 * this will determine which queue it is placed within so that some shorter jobs can execute immediately without needing
 * to wait for a potentially long running job.
 *
 * @property string $JobTitle Name of job
 * @property string $Signature Unique identifier for this job instance
 * @property string $Implementation Classname of underlying job
 * @property string $StartAfter Don't start until this date, if set
 * @property string $JobStarted When this job was started
 * @property string $JobFinished When this job was finished
 * @property int $TotalSteps Number of steps
 * @property int $StepsProcessed Number of completed steps
 * @property int $LastProcessedCount Number at which StepsProcessed was last checked for stalled jobs
 * @property int $ResumeCounts Number of times this job has been resumed
 * @property string $SavedJobData serialised data for the job to use as storage
 * @property string $SavedJobMessages List of messages saved for this job
 * @property string $JobStatus Status of this job
 * @property string $JobType Type of job
 * @property string $Worker
 * @property string $Expiry
 * @property bool $NotifiedBroken
 * @property int $WorkerCount
 *
 * @method Member RunAs() Member to run this job as
 *
 * @author Marcus Nyeholt <marcus@symbiote.com.au>
 * @license BSD http://silverstripe.org/bsd-license/
 */
class QueuedJobDescriptor extends DataObject
{
    /**
     * {@inheritDoc}
     * @var string
     */
    private static $table_name = 'QueuedJobDescriptor';

    /**
     * @var array
     */
    private static $db = [
        'JobTitle' => 'Varchar(255)',
        'Signature' => 'Varchar(64)',
        'Implementation' => 'Varchar(255)',
        'StartAfter' => 'DBDatetime',
        'JobStarted' => 'DBDatetime',
        'JobRestarted' => 'DBDatetime',
        'JobFinished' => 'DBDatetime',
        'TotalSteps' => 'Int',
        'StepsProcessed' => 'Int',
        'LastProcessedCount' => 'Int(-1)', // -1 means never checked, 0 means checked but no work is done
        'ResumeCounts' => 'Int',
        'SavedJobData' => 'Text',
        'SavedJobMessages' => 'Text',
        'JobStatus' => 'Varchar(16)',
        'JobType' => 'Varchar(16)',
        'Worker' => 'Varchar(32)',
        'Expiry' => 'DBDatetime',
        'NotifiedBroken' => 'Boolean',
        'WorkerCount' => 'Int',
    ];

    /**
     * @var array
     */
    private static $has_one = [
        'RunAs' => Member::class,
    ];

    /**
     * @var array
     */
    private static $defaults = [
        'JobStatus' => 'New',
        'ResumeCounts' => 0,
        'LastProcessedCount' => -1 // -1 means never checked, 0 means checked and none were processed
    ];

    /**
     * @var array
     */
    private static $indexes = [
        'JobStatus' => true,
        'StartAfter' => true,
        'Signature' => true,
    ];

    /**
     * @var array
     */
    private static $casting = [
        'Messages' => 'HTMLText',
    ];

    /**
     * @var array
     */
    private static $searchable_fields = [
        'JobTitle',
    ];

    /**
     * @var string
     */
    private static $default_sort = 'Created DESC';

    /**
     * Show job data and raw messages in the edit form
     *
     * @config
     * @var bool
     */
    private static $show_job_data = false;

    public function requireDefaultRecords()
    {
        parent::requireDefaultRecords();
        $this->getJobDir();
    }

    /**
     * @return array
     */
    public function summaryFields()
    {
        return [
            'JobTitle' => _t(__CLASS__ . '.TABLE_TITLE', 'Title'),
            'Created' => _t(__CLASS__ . '.TABLE_ADDE', 'Added'),
            'JobStarted' => _t(__CLASS__ . '.TABLE_STARTED', 'Started'),
            'JobFinished' => _t(__CLASS__ . '.TABLE_FINISHED', 'Finished'),
//          'JobRestarted' => _t(__CLASS__ . '.TABLE_RESUMED', 'Resumed'),
            'StartAfter' => _t(__CLASS__ . '.TABLE_START_AFTER', 'Start After'),
            'JobTypeString' => _t(__CLASS__ . '.JOB_TYPE', 'Job Type'),
            'JobStatus' => _t(__CLASS__ . '.TABLE_STATUS', 'Status'),
            'LastMessage' => _t(__CLASS__ . '.TABLE_MESSAGES', 'Message'),
            'StepsProcessed' => _t(__CLASS__ . '.TABLE_NUM_PROCESSED', 'Done'),
            'TotalSteps' => _t(__CLASS__ . '.TABLE_TOTAL', 'Total'),
        ];
    }

    /**
     * Pause this job, but only if it is waiting, running, or init
     *
     * @param bool $force Pause this job even if it's not waiting, running, or init
     *
     * @return bool Return true if this job was paused
     */
    public function pause($force = false)
    {
        if (
            $force || in_array(
                $this->JobStatus,
                [QueuedJob::STATUS_WAIT, QueuedJob::STATUS_RUN, QueuedJob::STATUS_INIT]
            )
        ) {
            $this->JobStatus = QueuedJob::STATUS_PAUSED;
            $this->write();
            return true;
        }
        return false;
    }

    /**
     * Resume this job and schedules it for execution
     *
     * @param bool $force Resume this job even if it's not paused or broken
     *
     * @return bool Return true if this job was resumed
     */
    public function resume($force = false)
    {
        if ($force || in_array($this->JobStatus, [QueuedJob::STATUS_PAUSED, QueuedJob::STATUS_BROKEN])) {
            $this->JobStatus = QueuedJob::STATUS_WAIT;
            $this->ResumeCounts++;
            $this->write();
            QueuedJobService::singleton()->startJob($this);
            return true;
        }
        return false;
    }

    /**
     * Restarts this job via a forced resume
     */
    public function restart()
    {
        $this->resume(true);
    }

    /**
     * Called to indicate that the job is ready to be run on the queue. This is done either as the result of
     * creating the job and adding it, or when resuming.
     */
    public function activateOnQueue()
    {
        // if it's an immediate job, lets cache it to disk to be picked up later
        if (
            $this->JobType == QueuedJob::IMMEDIATE
            && !Config::inst()->get(QueuedJobService::class, 'use_shutdown_function')
        ) {
            touch($this->getJobDir() . '/queuedjob-' . $this->ID);
        }
    }

    /**
     * Gets the path to the queuedjob cache directory
     *
     * @return string
     */
    protected function getJobDir()
    {
        // make sure our temp dir is in place. This is what will be inotify watched
        $jobDir = Config::inst()->get(QueuedJobService::class, 'cache_dir');
        if ($jobDir[0] !== '/') {
            $jobDir = TEMP_FOLDER . '/' . $jobDir;
        }

        if (!is_dir($jobDir)) {
            Filesystem::makeFolder($jobDir);
        }
        return $jobDir;
    }

    public function execute()
    {
        $service = QueuedJobService::singleton();
        $service->runJob($this->ID);
    }

    /**
     * Called when the job has completed and we want to cleanup anything the descriptor has lying around
     * in caches or the like.
     */
    public function cleanupJob()
    {
        // remove the job's temp file if it exists
        $tmpFile = $this->getJobDir() . '/queuedjob-' . $this->ID;
        if (file_exists($tmpFile)) {
            unlink($tmpFile);
        }
    }

    public function onBeforeDelete()
    {
        parent::onBeforeDelete();
        $this->cleanupJob();
    }

    /**
     * Get all job messages as an HTML unordered list.
     *
     * @return string|null
     */
    public function getMessages()
    {
        if (strlen($this->SavedJobMessages)) {
            $messages = @unserialize($this->SavedJobMessages);
            if (!empty($messages)) {
                return DBField::create_field(
                    'HTMLText',
                    '<ul><li>' . nl2br(implode('</li><li>', Convert::raw2xml($messages))) . '</li></ul>'
                );
            }
            return '';
        }
    }

    /**
     * Get the last job message as a raw string
     *
     * @return string|null
     */
    public function getLastMessage()
    {
        if (strlen($this->SavedJobMessages)) {
            $msgs = @unserialize($this->SavedJobMessages);
            if (is_array($msgs) && sizeof($msgs)) {
                return array_pop($msgs);
            }
        }
    }

    /**
     * @return string
     */
    public function getTitle()
    {
        return $this->JobTitle;
    }

    /**
     * Return a string representation of the numeric JobType
     * @return string
     */
    public function getJobTypeString()
    {
        $map = $this->getJobTypeValues();
        return isset($map[$this->JobType]) ? $map[$this->JobType] : '(Unknown)';
    }

    /**
     * @return string|null
     */
    public function getSavedJobDataPreview()
    {
        return $this->SavedJobData;
    }

    /**
     * @return string|null
     */
    public function getMessagesRaw()
    {
        return $this->SavedJobMessages;
    }

    /**
     * Return a map of numeric JobType values to localisable string representations.
     * @return array
     */
    public function getJobTypeValues()
    {
        return [
            QueuedJob::IMMEDIATE => _t(__CLASS__ . '.TYPE_IMMEDIATE', 'Immediate'),
            QueuedJob::QUEUED => _t(__CLASS__ . '.TYPE_QUEUED', 'Queued'),
            QueuedJob::LARGE => _t(__CLASS__ . '.TYPE_LARGE', 'Large'),
        ];
    }

    /**
     * List all possible job statuses, useful for forms and filters
     *
     * @return array
     */
    public function getJobStatusValues(): array
    {
        return [
            QueuedJob::STATUS_NEW,
            QueuedJob::STATUS_INIT,
            QueuedJob::STATUS_RUN,
            QueuedJob::STATUS_WAIT,
            QueuedJob::STATUS_COMPLETE,
            QueuedJob::STATUS_PAUSED,
            QueuedJob::STATUS_CANCELLED,
            QueuedJob::STATUS_BROKEN,
        ];
    }

    /**
     * @return FieldList
     */
    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $statuses = $this->getJobStatusValues();
        $runAs = $fields->fieldByName('Root.Main.RunAsID');

        $fields->removeByName([
            'Expiry',
            'Implementation',
            'JobTitle',
            'JobFinished',
            'JobRestarted',
            'JobType',
            'JobStarted',
            'JobStatus',
            'LastProcessedCount',
            'NotifiedBroken',
            'ResumeCounts',
            'RunAs',
            'RunAsID',
            'SavedJobData',
            'SavedJobMessages',
            'Signature',
            'StepsProcessed',
            'StartAfter',
            'TotalSteps',
            'Worker',
            'WorkerCount',
        ]);

        // Main
        $fields->addFieldsToTab('Root.Main', [
            LiteralField::create(
                'JobProgressReportIntro',
                sprintf(
                    '<p>%3$0.2f%% completed</p><p><progress value="%1$d" max="%2$d">%3$0.2f%%</progress></p>',
                    $this->StepsProcessed,
                    $this->TotalSteps,
                    $this->TotalSteps > 0 ? ($this->StepsProcessed / $this->TotalSteps) * 100 : 0
                )
            ),
            $jobTitle = TextField::create('JobTitle', 'Title'),
            $status = DropdownField::create('JobStatus', 'Status', array_combine($statuses, $statuses)),
            $jobType = DropdownField::create('JobType', 'Queue type', $this->getJobTypeValues()),
            $runAs,
            $startAfter = DatetimeField::create('StartAfter', 'Scheduled Start Time'),
            HeaderField::create('JobTimelineTitle', 'Timeline'),
            LiteralField::create(
                'JobTimelineIntro',
                sprintf(
                    '<p>%s</p>',
                    'It is recommended to avoid editing these fields'
                    . ' as they are managed by the Queue Runner / Service.'
                )
            ),
            $jobStarted = DatetimeField::create('JobStarted', 'Started (initial)'),
            $jobRestarted = DatetimeField::create('JobRestarted', 'Started (recent)'),
            $jobFinished = DatetimeField::create('JobFinished', 'Completed'),
        ]);

        $jobFinished->setDescription('Job completion time.');
        $jobRestarted->setDescription('Most recent attempt to run the job.');
        $jobStarted->setDescription('First attempt to run the job.');
        $jobType->setDescription('Type of Queue which the jobs belongs to.');
        $status->setDescription('Represents current state within the job lifecycle.');

        $jobTitle->setDescription(
            'This field can be used to hold user comments about specific jobs (no functional impact).'
        );

        $startAfter->setDescription(
            'Used to prevent the job from starting earlier than the specified time.'
            . ' Note that this does not guarantee that the job will start'
            . ' exactly at the specified time (it will start the next time the cron job runs).'
        );

        $runAs
            ->setTitle('Run With User')
            ->setDescription(
                'Select a user to be used to run this job.'
                . ' This should be used in case the changes done by this job'
                . ' have to look like the specified user made them.'
            );

        // Advanced
        $fields->addFieldsToTab('Root.Advanced', [
            HeaderField::create('AdvancedTabTitle', 'Advanced fields', 1),
            LiteralField::create(
                'AdvancedTabIntro',
                sprintf(
                    '<p>%s</p>',
                    'It is recommended to avoid editing these fields'
                    . ' as they are managed by the Queue Runner / Service.'
                )
            ),
            $implementation = TextField::create('Implementation', 'Job Class'),
            $signature = TextField::create('Signature', 'Job Signature'),
            $notifiedBroken = CheckboxField::create('NotifiedBroken', 'Broken job notification sent'),
            HeaderField::create('AdvancedTabProgressTitle', 'Progression metadata'),
            LiteralField::create(
                'AdvancedTabProgressIntro',
                sprintf(
                    '<p>%s</p>',
                    'Job progression mechanism related fields which are used to'
                    . ' ensure that stalled jobs are paused / resumed.'
                )
            ),
            $totalSteps = NumericField::create('TotalSteps', 'Steps Total'),
            $stepsProcessed = NumericField::create('StepsProcessed', 'Steps Processed'),
            $lastProcessCount = NumericField::create('LastProcessedCount', 'Steps Processed (previous)'),
            $resumeCount = NumericField::create('ResumeCounts', 'Resume Count'),
            HeaderField::create('AdvancedTabLockTitle', 'Lock metadata'),
            LiteralField::create(
                'AdvancedTabLockTitleIntro',
                sprintf(
                    '<p>%s</p>',
                    'Job locking mechanism related fields which are used to'
                    . ' ensure that every job gets executed only once at any given time.'
                )
            ),
            $worker = TextField::create('Worker', 'Worker Signature'),
            $workerCount = NumericField::create('WorkerCount', 'Worker Count'),
            $expiry = DatetimeField::create('Expiry', 'Lock Expiry'),
        ]);

        $implementation->setDescription('Class name which is used to execute this job.');
        $notifiedBroken->setDescription('Indicates if a broken job notification was sent (this happens only once).');
        $totalSteps->setDescription('Number of steps which is needed to complete this job.');
        $stepsProcessed->setDescription('Number of steps processed so far.');
        $workerCount->setDescription('Number of workers (processes) used to execute this job overall.');
        $worker->setDescription(
            'Used by a worker (process) to claim this job which prevents any other process from claiming it.'
        );

        $lastProcessCount->setDescription(
            'Steps Processed value from previous execution of this job'
            . ', used to compare against current state of the steps to determine the difference (progress).'
        );

        $signature->setDescription(
            'Usualy derived from the job data, prevents redundant jobs from being created to some degree.'
        );

        $resumeCount->setDescription(
            sprintf(
                'Number of times this job stalled and was resumed (limit of %d time(s)).',
                QueuedJobService::singleton()->config()->get('stall_threshold')
            )
        );

        $expiry->setDescription(
            sprintf(
                'Specifies when the lock is released (lock expires %d seconds after the job is claimed).',
                $this->getWorkerExpiry()
            )
        );

        if (strlen($this->SavedJobMessages)) {
            $fields->addFieldToTab('Root.Messages', LiteralField::create('Messages', $this->getMessages()));
        }

        if ($this->config()->get('show_job_data')) {
            $fields->addFieldsToTab('Root.JobData', [
                $jobDataPreview = TextareaField::create('SavedJobDataPreview', 'Job Data'),
            ]);

            $jobDataPreview->setReadonly(true);

            $fields->addFieldsToTab('Root.MessagesRaw', [
                $messagesRaw = TextareaField::create('MessagesRaw', 'Messages Raw'),
            ]);

            $messagesRaw->setReadonly(true);
        }

        if (Permission::check('ADMIN')) {
            return $fields;
        }

        // Readonly CMS view is a lot more useful for debugging than no view at all
        return $fields->makeReadonly();
    }

    private function getWorkerExpiry()
    {
        $now = DBDatetime::now();
        $time = new DateTime($now->Rfc2822());
        $timeToLive = QueuedJobService::singleton()->config()->get('worker_ttl');

        if ($timeToLive) {
            $time->add(new DateInterval($timeToLive));
        }

        return $time->getTimestamp() - $now->getTimestamp();
    }
}

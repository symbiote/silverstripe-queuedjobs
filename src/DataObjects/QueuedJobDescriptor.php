<?php

namespace Symbiote\QueuedJobs\DataObjects;

use SilverStripe\Assets\Filesystem;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Convert;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\Security\Permission;
use Symbiote\QueuedJobs\Services\QueuedJob;
use Symbiote\QueuedJobs\Services\QueuedJobService;

/**
 * A QueuedJobDescriptor is the stored representation of a piece of work that could take a while to execute,
 * because of which it is desireable to not have it executing in parallel to other jobs.
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
    ];

    /**
     * @var array
     */
    private static $has_one = [
        'RunAs' => 'SilverStripe\\Security\\Member',
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
        return array(
            'JobTitle' => _t(__CLASS__ . '.TABLE_TITLE', 'Title'),
            'Created' => _t(__CLASS__ . '.TABLE_ADDE', 'Added'),
            'JobStarted' => _t(__CLASS__ . '.TABLE_STARTED', 'Started'),
//          'JobRestarted' => _t(__CLASS__ . '.TABLE_RESUMED', 'Resumed'),
            'StartAfter' => _t(__CLASS__ . '.TABLE_START_AFTER', 'Start After'),
            'JobType'   => _t(__CLASS__ . '.JOB_TYPE', 'Job Type'),
            'JobStatus' => _t(__CLASS__ . '.TABLE_STATUS', 'Status'),
            'LastMessage' => _t(__CLASS__ . '.TABLE_MESSAGES', 'Message'),
            'StepsProcessed' => _t(__CLASS__ . '.TABLE_NUM_PROCESSED', 'Done'),
            'TotalSteps' => _t(__CLASS__ . '.TABLE_TOTAL', 'Total'),
        );
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
        if ($force || in_array(
                $this->JobStatus,
                [QueuedJob::STATUS_WAIT, QueuedJob::STATUS_RUN, QueuedJob::STATUS_INIT]
            )) {
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
            singleton(QueuedJobService::class)->startJob($this);
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
        if ($this->JobType == QueuedJob::IMMEDIATE
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
        if ($jobDir{0} != '/') {
            $jobDir = TEMP_FOLDER . '/' . $jobDir;
        }

        if (!is_dir($jobDir)) {
            Filesystem::makeFolder($jobDir);
        }
        return $jobDir;
    }

    public function execute()
    {
        $service = singleton(QueuedJobService::class);
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
     * @return string|void
     */
    public function getMessages()
    {
        if (strlen($this->SavedJobMessages)) {
            $messages = @unserialize($this->SavedJobMessages);
            if (!empty($messages)) {
                return DBField::create_field('HTMLText', '<ul><li>' . nl2br(implode('</li><li>', Convert::raw2xml($messages))) . '</li></ul>');
            }
            return '';
        }
    }

    /**
     * Get the last job message as a raw string
     *
     * @return string|void
     */
    public function getLastMessage()
    {
        if (strlen($this->SavedJobMessages)) {
            $msgs = @unserialize($this->SavedJobMessages);
            if (is_array($msgs) && sizeof($msgs)) {
                $msg = array_pop($msgs);
                return $msg;
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
     * @return FieldList
     */
    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $fields->replaceField(
            'JobType',
            new DropdownField('JobType', $this->fieldLabel('JobType'), [
                QueuedJob::IMMEDIATE => 'Immediate',
                QueuedJob::QUEUED => 'Queued',
                QueuedJob::LARGE => 'Large',
            ])
        );
        $statuses = [
            QueuedJob::STATUS_NEW,
            QueuedJob::STATUS_INIT,
            QueuedJob::STATUS_RUN,
            QueuedJob::STATUS_WAIT,
            QueuedJob::STATUS_COMPLETE,
            QueuedJob::STATUS_PAUSED,
            QueuedJob::STATUS_CANCELLED,
            QueuedJob::STATUS_BROKEN,
        ];
        $fields->replaceField(
            'JobStatus',
            DropdownField::create('JobStatus', $this->fieldLabel('JobStatus'), array_combine($statuses, $statuses))
        );

        $fields->removeByName('SavedJobData');
        $fields->removeByName('SavedJobMessages');

        if (strlen($this->SavedJobMessages)) {
            $fields->addFieldToTab('Root.Messages', new LiteralField('Messages', $this->getMessages()));
        }

        if (Permission::check('ADMIN')) {
            return $fields;
        }

        // Readonly CMS view is a lot more useful for debugging than no view at all
        return $fields->makeReadonly();
    }
}

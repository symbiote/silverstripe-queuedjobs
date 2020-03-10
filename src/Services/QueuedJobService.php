<?php

namespace Symbiote\QueuedJobs\Services;

use DateInterval;
use DateTime;
use Exception;
use Monolog\Handler\BufferHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Convert;
use SilverStripe\Core\Extensible;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\ORM\Queries\SQLUpdate;
use SilverStripe\ORM\ValidationException;
use SilverStripe\Security\Member;
use SilverStripe\Security\Security;
use SilverStripe\Subsites\Model\Subsite;
use Symbiote\QueuedJobs\DataObjects\QueuedJobDescriptor;
use Symbiote\QueuedJobs\Interfaces\UserContextInterface;
use Symbiote\QueuedJobs\QJUtils;
use Symbiote\QueuedJobs\Tasks\Engines\TaskRunnerEngine;

/**
 * A service that can be used for starting, stopping and listing queued jobs.
 *
 * When a job is first added, it is initialised, its job type determined, then persisted to the database
 *
 * When the queues are scanned, a job is reloaded and processed. Ignoring the persistence and reloading, it looks
 * something like
 *
 * job->getJobType();
 * job->getJobData();
 * data->write();
 * job->setup();
 * while !job->isComplete
 *  job->process();
 *  job->getJobData();
 *  data->write();
 *
 *
 * @author Marcus Nyeholt <marcus@symbiote.com.au>
 * @license BSD http://silverstripe.org/bsd-license/
 */
class QueuedJobService
{
    use Configurable;
    use Injectable;
    use Extensible;

    /**
     * @config
     * @var int
     */
    private static $stall_threshold = 3;

    /**
     * How much ram will we allow before pausing and releasing the memory?
     *
     * For instance, set to 268435456 (256MB) to pause this process if used memory exceeds
     * this value. This needs to be set to a value lower than the php_ini max_memory as
     * the system will otherwise crash before shutdown can be handled gracefully.
     *
     * This was increased to 256MB for SilverStripe 4.x as framework uses more memory than 3.x
     *
     * @var int
     * @config
     */
    private static $memory_limit = 268435456;

    /**
     * Optional time limit (in seconds) to run the service before restarting to release resources.
     *
     * Defaults to no limit.
     *
     * @var int
     * @config
     */
    private static $time_limit = 0;

    /**
     * Disable health checks that usually occur when a runner first picks up a queue. Note that completely disabling
     * health checks could result in many jobs that are always marked as running - that will never be restarted. If
     * this option is disabled you may alternatively use the build task
     *
     * @see \Symbiote\QueuedJobs\Tasks\CheckJobHealthTask
     *
     * @var bool
     * @config
     */
    private static $disable_health_check = false;

    /**
     * Maximum number of jobs that can be initialised at any one time.
     *
     * Prevents too many jobs getting into this state in case something goes wrong with the child processes.
     * We shouldn't have too many jobs in the initialising state anyway.
     *
     * Valid values:
     * 0 - unlimited (default)
     * greater than 0 - maximum number of jobs in initialised state
     *
     * @var int
     * @config
     */
    private static $max_init_jobs = 0;

    /**
     * Duration for TTL of queue workers based on ISO 8601 duration specification.
     * Defaults to 5 minutes.
     *
     * @var string
     */
    private static $worker_ttl = 'PT5M';

    /**
     * Timestamp (in seconds) when the queue was started
     *
     * @var int
     */
    protected $startedAt = 0;

    /**
     * Should "immediate" jobs be managed using the shutdown function?
     *
     * It is recommended you set up an inotify watch and use that for
     * triggering immediate jobs. See the wiki for more information
     *
     * @var boolean
     * @config
     */
    private static $use_shutdown_function = true;

    /**
     * The location for immediate jobs to be stored in
     *
     * @var string
     * @config
     */
    private static $cache_dir = 'queuedjobs';

    /**
     * Maintenance lock file feature enabled / disable setting
     *
     * @config
     * @var bool
     */
    private static $lock_file_enabled = false;

    /**
     * Maintenance lock file name
     *
     * @config
     * @var string
     */
    private static $lock_file_name = 'maintenance-lock.txt';

    /**
     * Maintenance lock path (relative path starting at the base folder)
     * Note that this path needs to point to a folder on a shared drive if multiple instances are used
     *
     * @config
     * @var string
     */
    private static $lock_file_path = '';

    /**
     * @var DefaultQueueHandler
     */
    public $queueHandler;

    /**
     *
     * @var TaskRunnerEngine
     */
    public $queueRunner;

    /**
     * Config controlled list of default/required jobs
     *
     * @var array
     */
    public $defaultJobs = [];

    /**
     * QueuedJobService constructor.
     */
    public function __construct()
    {
        if (!$this->config()->get('use_shutdown_function') || !Director::is_cli()) {
            return;
        }

        // Register our shutdown handler
        // bind a shutdown function to process all 'immediate' queued jobs if needed, but only in CLI mode
        register_shutdown_function([$this, 'onShutdown']);
    }

    /**
     * Adds a job to the queue to be started
     *
     * @param QueuedJob $job The job to start.
     * @param null|string $startAfter The date (in Y-m-d H:i:s format) to start execution after
     * @param null|int $userId The ID of a user to execute the job as. Defaults to the current user
     * @param null|int $queueName
     * @return int
     * @throws ValidationException
     */
    public function queueJob(QueuedJob $job, $startAfter = null, $userId = null, $queueName = null)
    {
        $signature = $job->getSignature();

        // see if we already have this job in a queue
        $filter = [
            'Signature' => $signature,
            'JobStatus' => [
                QueuedJob::STATUS_NEW,
                QueuedJob::STATUS_INIT,
            ],
        ];

        $existing = QueuedJobDescriptor::get()
            ->filter($filter)
            ->first();

        if ($existing && $existing->ID) {
            return $existing->ID;
        }

        $jobDescriptor = new QueuedJobDescriptor();
        $jobDescriptor->JobTitle = $job->getTitle();
        $jobDescriptor->JobType = $queueName ? $queueName : $job->getJobType();
        $jobDescriptor->Signature = $signature;
        $jobDescriptor->Implementation = get_class($job);
        $jobDescriptor->StartAfter = $startAfter;

        // no user provided - fallback to job user default
        if ($userId === null && $job instanceof UserContextInterface) {
            $userId = $job->getRunAsMemberID();
        }

        // still no user - fallback to current user
        if ($userId === null) {
            if (Security::getCurrentUser() && Security::getCurrentUser()->exists()) {
                // current user available
                $runAsID = Security::getCurrentUser()->ID;
            } else {
                // current user unavailable
                $runAsID = 0;
            }
        } else {
            $runAsID = $userId;
        }

        $jobDescriptor->RunAsID = $runAsID;

        // use this to populate custom data columns before job is queued
        // note: you can pass arbitrary data to your job and then move it to job descriptor
        // this is useful if you need some data that needs to be exposed as a separate
        // DB column as opposed to serialised data
        $this->extend('updateJobDescriptorBeforeQueued', $jobDescriptor, $job);

        // copy data
        $this->copyJobToDescriptor($job, $jobDescriptor);

        $jobDescriptor->write();

        $this->startJob($jobDescriptor, $startAfter);

        return $jobDescriptor->ID;
    }

    /**
     * Start a job (or however the queue handler determines it should be started)
     *
     * @param QueuedJobDescriptor $jobDescriptor
     * @param string $startAfter
     */
    public function startJob($jobDescriptor, $startAfter = null)
    {
        if ($startAfter && strtotime($startAfter) > DBDatetime::now()->getTimestamp()) {
            $this->queueHandler->scheduleJob($jobDescriptor, $startAfter);
        } else {
            // immediately start it on the queue, however that works
            $this->queueHandler->startJobOnQueue($jobDescriptor);
        }
    }

    /**
     * Check if maximum number of jobs are currently initialised.
     *
     * @return bool
     */
    public function isAtMaxJobs()
    {
        $initJobsMax = $this->config()->get('max_init_jobs');
        if (!$initJobsMax) {
            return false;
        }

        $initJobsCount = QueuedJobDescriptor::get()
            ->filter(['JobStatus' => QueuedJob::STATUS_INIT])
            ->count();

        if ($initJobsCount >= $initJobsMax) {
            return true;
        }

        return false;
    }

    /**
     * Copies data from a job into a descriptor for persisting
     *
     * @param QueuedJob $job
     * @param QueuedJobDescriptor $jobDescriptor
     */
    protected function copyJobToDescriptor($job, $jobDescriptor)
    {
        $data = $job->getJobData();

        $jobDescriptor->TotalSteps = $data->totalSteps;
        $jobDescriptor->StepsProcessed = $data->currentStep;
        if ($data->isComplete) {
            $jobDescriptor->JobStatus = QueuedJob::STATUS_COMPLETE;
            $jobDescriptor->JobFinished = DBDatetime::now()->Rfc2822();
        }

        $jobDescriptor->SavedJobData = serialize($data->jobData);
        $jobDescriptor->SavedJobMessages = serialize($data->messages);
    }

    /**
     * @param QueuedJobDescriptor $jobDescriptor
     * @param QueuedJob $job
     */
    protected function copyDescriptorToJob($jobDescriptor, $job)
    {
        $jobData = null;
        $messages = null;

        // switching to php's serialize methods... not sure why this wasn't done from the start!
        $jobData = @unserialize($jobDescriptor->SavedJobData);
        $messages = @unserialize($jobDescriptor->SavedJobMessages);

        // try decoding as json if null
        $jobData = $jobData ?: json_decode($jobDescriptor->SavedJobData);
        $messages = $messages ?: json_decode($jobDescriptor->SavedJobMessages);

        $job->setJobData(
            $jobDescriptor->TotalSteps,
            $jobDescriptor->StepsProcessed,
            $jobDescriptor->JobStatus == QueuedJob::STATUS_COMPLETE,
            $jobData,
            $messages
        );
    }

    /**
     * Check the current job queues and see if any of the jobs currently in there should be started. If so,
     * return the next job that should be executed
     *
     * @param string $type Job type
     *
     * @return QueuedJobDescriptor|false
     */
    public function getNextPendingJob($type = null)
    {
        // Filter jobs by type
        $type = $type ?: QueuedJob::QUEUED;
        $list = QueuedJobDescriptor::get()
            ->filter([
                'JobType' => $type,
                'Worker:ExactMatch' => null,
            ])
            ->sort('ID', 'ASC');

        // see if there's any blocked jobs that need to be resumed
        /** @var QueuedJobDescriptor $waitingJob */
        $waitingJob = $list->find('JobStatus', QueuedJob::STATUS_WAIT);

        if ($waitingJob) {
            return $waitingJob;
        }

        // Otherwise, lets find any 'new' jobs that are waiting to execute
        /** @var QueuedJobDescriptor $newJob */
        $newJob = $list
            ->filter('JobStatus', QueuedJob::STATUS_NEW)
            ->where(sprintf(
                '"StartAfter" < \'%s\' OR "StartAfter" IS NULL',
                DBDatetime::now()->getValue()
            ))
            ->first();

        return $newJob;
    }

    /**
     * Runs an explicit check on all currently running jobs to make sure their "processed" count is incrementing
     * between each run. If it's not, then we need to flag it as paused due to an error.
     *
     * This typically happens when a PHP fatal error is thrown, which can't be picked up by the error
     * handler or exception checker; in this case, we detect these stalled jobs later and fix (try) to
     * fix them
     *
     * This function returns the IDs of stalled and broken jobs
     * this information can be used to implement some custom follow up actions such as sending email reports
     *
     * @param int $queue The queue to check against
     * @return array stalled job and broken job IDs
     */
    public function checkJobHealth($queue = null)
    {
        $queue = $queue ?: QueuedJob::QUEUED;

        // Select all jobs currently marked as running
        $runningJobs = QueuedJobDescriptor::get()
            ->filter([
                'JobStatus' => [
                    QueuedJob::STATUS_RUN,
                    QueuedJob::STATUS_INIT,
                ],
                'JobType' => $queue,
            ]);

        // If no steps have been processed since the last run, consider it a broken job
        // Only check jobs that have been viewed before. LastProcessedCount defaults to -1 on new jobs.
        // Only check jobs that are past expiry to ensure another process isn't currently executing the job
        $now = DBDatetime::now()->Rfc2822();
        $stalledJobs = $runningJobs
            ->filter([
                'LastProcessedCount:GreaterThanOrEqual' => 0,
                'Expiry:LessThanOrEqual' => $now,
            ])
            ->where('"StepsProcessed" = "LastProcessedCount"');

        foreach ($stalledJobs as $stalledJob) {
            $this->restartStalledJob($stalledJob);
        }

        // now, find those that need to be marked before the next check
        // foreach job, mark it as having been incremented
        foreach ($runningJobs as $job) {
            /** @var QueuedJobDescriptor $job */
            $job->LastProcessedCount = $job->StepsProcessed;
            $job->write();
        }

        // finally, find the list of broken jobs and send an email if there's some found
        // make sure that we report broken job only once
        $brokenJobs = QueuedJobDescriptor::get()->filter([
            'JobStatus' => QueuedJob::STATUS_BROKEN,
            'NotifiedBroken' => 0,
        ]);

        $stalledIDs = $stalledJobs->column('ID');
        $brokenIDs = $brokenJobs->column('ID');
        $result = [
            'stalled' => $stalledIDs,
            'broken' => $brokenIDs,
        ];

        if (count($brokenIDs) === 0) {
            return $result;
        }

        $this->getLogger()->error(
            print_r(
                [
                    'errno' => 0,
                    'errstr' => 'Broken jobs were found in the job queue',
                    'errfile' => __FILE__,
                    'errline' => __LINE__,
                    'errcontext' => [],
                ],
                true
            ),
            [
                'file' => __FILE__,
                'line' => __LINE__,
            ]
        );

        $placeholders = implode(', ', array_fill(0, count($brokenIDs), '?'));
        $query = SQLUpdate::create(
            '"QueuedJobDescriptor"',
            ['"NotifiedBroken"' => 1],
            ['"ID" IN (' . $placeholders . ')' => $brokenIDs]
        );

        $query->execute();

        return $result;
    }

    /**
     * Checks through ll the scheduled jobs that are expected to exist
     */
    public function checkDefaultJobs($queue = null)
    {
        $queue = $queue ?: QueuedJob::QUEUED;
        if (count($this->defaultJobs)) {
            $activeJobs = QueuedJobDescriptor::get()->filter(
                'JobStatus',
                [
                    QueuedJob::STATUS_NEW,
                    QueuedJob::STATUS_INIT,
                    QueuedJob::STATUS_RUN,
                    QueuedJob::STATUS_WAIT,
                    QueuedJob::STATUS_PAUSED,
                ]
            );
            foreach ($this->defaultJobs as $title => $jobConfig) {
                if (!isset($jobConfig['filter']) || !isset($jobConfig['type'])) {
                    $this->getLogger()->error(
                        "Default Job config: $title incorrectly set up. Please check the readme for examples",
                        [
                            'file' => __FILE__,
                            'line' => __LINE__,
                        ]
                    );
                    continue;
                }
                $job = $activeJobs->filter(array_merge(
                    ['Implementation' => $jobConfig['type']],
                    $jobConfig['filter']
                ));
                if (!$job->count()) {
                    $this->getLogger()->info(
                        "Default Job config: $title was missing from Queue",
                        [
                            'file' => __FILE__,
                            'line' => __LINE__,
                        ]
                    );

                    $email = EmailService::singleton()->createMissingDefaultJobReport($jobConfig, $title);
                    if ($email !== null) {
                        $email->send();
                    }

                    if (isset($jobConfig['recreate']) && $jobConfig['recreate']) {
                        if (
                            !array_key_exists('construct', $jobConfig)
                            || !isset($jobConfig['startDateFormat'])
                            || !isset($jobConfig['startTimeString'])
                        ) {
                            $this->getLogger()->error(
                                "Default Job config: $title incorrectly set up. Please check the readme for examples",
                                [
                                    'file' => __FILE__,
                                    'line' => __LINE__,
                                ]
                            );
                            continue;
                        }
                        QueuedJobService::singleton()->queueJob(
                            Injector::inst()->createWithArgs($jobConfig['type'], $jobConfig['construct']),
                            date($jobConfig['startDateFormat'], strtotime($jobConfig['startTimeString']))
                        );
                        $this->getLogger()->info(
                            "Default Job config: $title has been re-added to the Queue",
                            [
                                'file' => __FILE__,
                                'line' => __LINE__,
                            ]
                        );
                    }
                }
            }
        }
    }

    /**
     * Attempt to restart a stalled job
     *
     * @param QueuedJobDescriptor $stalledJob
     */
    protected function restartStalledJob($stalledJob)
    {
        // release job lock on the descriptor so it can run again
        $stalledJob->Worker = null;

        if ($stalledJob->ResumeCounts < static::config()->get('stall_threshold')) {
            $stalledJob->restart();
            $logLevel = 'warning';
            $message = _t(
                __CLASS__ . '.STALLED_JOB_RESTART_MSG',
                'A job named {name} (#{id}) appears to have stalled. It will be stopped and restarted, please '
                . 'login to make sure it has continued',
                ['name' => $stalledJob->JobTitle, 'id' => $stalledJob->ID]
            );
        } else {
            $stalledJob->pause();
            $logLevel = 'error';
            $message = _t(
                __CLASS__ . '.STALLED_JOB_MSG',
                'A job named {name} (#{id}) appears to have stalled. It has been paused, please login to check it',
                ['name' => $stalledJob->JobTitle, 'id' => $stalledJob->ID]
            );
        }

        $this->getLogger()->log(
            $logLevel,
            $message,
            [
                'file' => __FILE__,
                'line' => __LINE__,
            ]
        );

        $subject = _t(__CLASS__ . '.STALLED_JOB', 'Stalled job');
        $email = EmailService::singleton()->createStalledJobReport($subject, $message, (int) $stalledJob->ID);

        if ($email) {
            $email->send();
        }
    }

    /**
     * Prepares the given jobDescriptor for execution. Returns the job that
     * will actually be run in a state ready for executing.
     *
     * Note that this is called each time a job is picked up to be executed from the cron
     * job - meaning that jobs that are paused and restarted will have 'setup()' called on them again,
     * so your job MUST detect that and act accordingly.
     *
     * @param QueuedJobDescriptor $jobDescriptor
     *          The Job descriptor of a job to prepare for execution
     *
     * @return QueuedJob|boolean
     * @throws Exception
     */
    protected function initialiseJob(QueuedJobDescriptor $jobDescriptor)
    {
        // create the job class
        $impl = $jobDescriptor->Implementation;
        /** @var QueuedJob $job */
        $job = Injector::inst()->create($impl);
        /* @var $job QueuedJob */
        if (!$job) {
            throw new Exception("Implementation $impl no longer exists");
        }

        $jobDescriptor->JobStatus = QueuedJob::STATUS_INIT;
        $jobDescriptor->write();

        // make sure the data is there
        $this->copyDescriptorToJob($jobDescriptor, $job);

        // see if it needs 'setup' or 'restart' called
        if ($jobDescriptor->StepsProcessed <= 0) {
            $job->setup();
        } else {
            $job->prepareForRestart();
        }

        // make sure the descriptor is up to date with anything changed
        $this->copyJobToDescriptor($job, $jobDescriptor);
        $jobDescriptor->write();

        return $job;
    }

    /**
     * Given a {@link QueuedJobDescriptor} mark the job as initialised. Works sort of like a mutex.
     * Currently a database lock isn't entirely achievable, due to database adapters not supporting locks.
     * This may still have a race condition, but this should minimise the possibility.
     * Side effect is the job status will be changed to "Initialised".
     *
     * Assumption is the job has a status of "Queued" or "Wait".
     *
     * @param QueuedJobDescriptor $jobDescriptor
     *
     * @return bool
     */
    protected function grabMutex(QueuedJobDescriptor $jobDescriptor)
    {
        $descriptorId = (int) $jobDescriptor->ID;

        try {
            // Start a transaction which will hold until we have a lock on this descriptor.
            DB::get_conn()->withTransaction(function () use ($descriptorId) {
                $query = 'SELECT "ID" FROM "QueuedJobDescriptor" WHERE "ID" = %s AND "Worker" IS NULL FOR UPDATE';

                $row = DB::query(sprintf($query, Convert::raw2sql($descriptorId)))->first();

                if (!array_key_exists('ID', $row) || !$row['ID']) {
                    throw new Exception('Failed to read job lock');
                }

                $mutex = bin2hex(random_bytes(16));
                $expiry = $this->getWorkerExpiry();

                // Lock this descriptor.
                $query = 'UPDATE "QueuedJobDescriptor" SET "Expiry" = %s , "Worker" = %s'
                    . ', "WorkerCount" = "WorkerCount" + 1 WHERE "ID" = %s';

                DB::query(sprintf(
                    $query,
                    Convert::raw2sql($expiry, true),
                    Convert::raw2sql($mutex, true),
                    Convert::raw2sql($descriptorId)
                ));

                /** @var QueuedJobDescriptor $updatedDescriptor */
                $updatedDescriptor = QueuedJobDescriptor::get()->byID($descriptorId);

                // If we couldn't find the descriptor or the descriptor is not the one we expect to have
                if ($updatedDescriptor === null || $updatedDescriptor->Worker !== $mutex) {
                    throw new Exception('Wrong status or process. Job reserved already');
                }
            });

            return true;
        } catch (Exception $e) {
            // note that error here may not be an issue as failing to acquire a job lock is a valid state
            // which happens when other process claimed the job lock first
            $this->getLogger()->debug(
                sprintf(
                    '[%s] - Queued Jobs - Failed to acquire job lock %s %d %s',
                    DBDatetime::now()->Rfc2822(),
                    $e->getMessage(),
                    $descriptorId,
                    PHP_EOL
                ),
                [
                    'file' => __FILE__,
                    'line' => __LINE__,
                ]
            );

            return false;
        }
    }

    /**
     * Start the actual execution of a job.
     * The assumption is the jobID refers to a {@link QueuedJobDescriptor} that is status set as "Queued".
     *
     * This method will continue executing until the job says it's completed
     *
     * @param int $jobId
     *          The ID of the job to start executing
     *
     * @return boolean
     * @throws Exception
     */
    public function runJob($jobId)
    {
        // first retrieve the descriptor
        /** @var QueuedJobDescriptor $jobDescriptor */
        $jobDescriptor = DataObject::get_by_id(
            QueuedJobDescriptor::class,
            (int)$jobId
        );
        if (!$jobDescriptor) {
            throw new Exception("$jobId is invalid");
        }

        // now lets see whether we have a current user to run as. Typically, if the job is executing via the CLI,
        // we want it to actually execute as the RunAs user - however, if running via the web (which is rare...), we
        // want to ensure that the current user has admin privileges before switching. Otherwise, we just run it
        // as the currently logged in user and hope for the best

        // We need to use $_SESSION directly because SS ties the session to a controller that no longer exists at
        // this point of execution in some circumstances
        $originalUserID = isset($_SESSION['loggedInAs']) ? $_SESSION['loggedInAs'] : 0;
        /** @var Member|null $originalUser */
        $originalUser = $originalUserID
            ? DataObject::get_by_id(Member::class, $originalUserID)
            : null;
        $runAsUser = null;

        // If the Job has requested that we run it as a particular user, then we should try and do that.
        if ($jobDescriptor->RunAs() !== null) {
            $runAsUser = $this->setRunAsUser($jobDescriptor->RunAs(), $originalUser);
        }

        $broken = false;

        $this->withNestedState(function () use ($jobDescriptor, $jobId, &$broken) {
            if (!$this->grabMutex($jobDescriptor)) {
                return;
            }

            $job = null;

            try {
                $job = $this->initialiseJob($jobDescriptor);

                // get the job ready to begin.
                if (!$jobDescriptor->JobStarted) {
                    $jobDescriptor->JobStarted = DBDatetime::now()->Rfc2822();
                } else {
                    $jobDescriptor->JobRestarted = DBDatetime::now()->Rfc2822();
                }

                // Only write to job as "Running" if 'isComplete' was NOT set to true
                // during setup() or prepareForRestart()
                if (!$job->jobFinished()) {
                    $jobDescriptor->JobStatus = QueuedJob::STATUS_RUN;
                    $jobDescriptor->write();
                }

                $lastStepProcessed = 0;
                // have we stalled at all?
                $stallCount = 0;

                if (class_exists(Subsite::class) && !empty($job->SubsiteID)) {
                    Subsite::changeSubsite($job->SubsiteID);

                    // lets set the base URL as far as Director is concerned so that our URLs are correct
                    /** @var Subsite $subsite */
                    $subsite = DataObject::get_by_id(Subsite::class, $job->SubsiteID);
                    if ($subsite && $subsite->exists()) {
                        $domain = $subsite->domain();
                        $base = rtrim(Director::protocol() . $domain, '/') . '/';

                        Config::modify()->set(Director::class, 'alternate_base_url', $base);
                    }
                }

                // while not finished
                while (!$job->jobFinished() && !$broken) {
                    // see that we haven't been set to 'paused' or otherwise by another process
                    /** @var QueuedJobDescriptor $jobDescriptor */
                    $jobDescriptor = DataObject::get_by_id(
                        QueuedJobDescriptor::class,
                        (int)$jobId
                    );
                    if (!$jobDescriptor || !$jobDescriptor->exists()) {
                        $broken = true;
                        $this->getLogger()->error(
                            print_r(
                                [
                                    'errno' => 0,
                                    'errstr' => 'Job descriptor ' . $jobId . ' could not be found',
                                    'errfile' => __FILE__,
                                    'errline' => __LINE__,
                                    'errcontext' => [],
                                ],
                                true
                            ),
                            [
                                'file' => __FILE__,
                                'line' => __LINE__,
                            ]
                        );
                        break;
                    }
                    if ($jobDescriptor->JobStatus != QueuedJob::STATUS_RUN) {
                        // we've been paused by something, so we'll just exit
                        $job->addMessage(_t(
                            __CLASS__ . '.JOB_PAUSED',
                            'Job paused at {time}',
                            ['time' => DBDatetime::now()->Rfc2822()]
                        ));
                        $broken = true;
                    }

                    if (!$broken) {
                        // Inject real-time log handler
                        $logger = Injector::inst()->get(LoggerInterface::class);
                        if ($logger instanceof Logger) {
                            // Check if there is already a handler
                            $exists = false;
                            foreach ($logger->getHandlers() as $handler) {
                                if ($handler instanceof QueuedJobHandler) {
                                    $exists = true;
                                    break;
                                }
                            }

                            if (!$exists) {
                                // Add the handler
                                /** @var QueuedJobHandler $queuedJobHandler */
                                $queuedJobHandler = QueuedJobHandler::create($job, $jobDescriptor);

                                // We only write for every 100 file
                                $bufferHandler = new BufferHandler(
                                    $queuedJobHandler,
                                    100,
                                    Logger::DEBUG,
                                    true,
                                    true
                                );

                                $logger->pushHandler($bufferHandler);
                            }
                        } else {
                            if ($logger instanceof LoggerInterface) {
                                $logger->warning(
                                    'Monolog not found, messages will not output while the job is running'
                                );
                            }
                        }

                        // Collect output as job messages as well as sending it to the screen after processing
                        $obLogger = function ($buffer, $phase) use ($job, $jobDescriptor) {
                            $job->addMessage($buffer);
                            if ($jobDescriptor) {
                                $this->copyJobToDescriptor($job, $jobDescriptor);
                                $jobDescriptor->write();
                            }
                            return $buffer;
                        };
                        ob_start($obLogger, 256);

                        try {
                            $job->process();
                        } catch (Exception $e) {
                            // okay, we'll just catch this exception for now
                            $job->addMessage(
                                _t(
                                    __CLASS__ . '.JOB_EXCEPT',
                                    'Job caused exception {message} in {file} at line {line}',
                                    [
                                        'message' => $e->getMessage(),
                                        'file' => $e->getFile(),
                                        'line' => $e->getLine(),
                                    ]
                                )
                            );
                            $this->getLogger()->error(
                                $e->getMessage(),
                                [
                                    'exception' => $e,
                                ]
                            );
                            $jobDescriptor->JobStatus =  QueuedJob::STATUS_BROKEN;
                            $this->extend('updateJobDescriptorAndJobOnException', $jobDescriptor, $job, $e);
                        }

                        // Write any remaining batched messages at the end
                        if (isset($bufferHandler)) {
                            $bufferHandler->flush();
                        }

                        ob_end_flush();

                        // now check the job state
                        $data = $job->getJobData();
                        if ($data->currentStep == $lastStepProcessed) {
                            $stallCount++;
                        }

                        if ($stallCount > static::config()->get('stall_threshold')) {
                            $broken = true;
                            $job->addMessage(
                                _t(
                                    __CLASS__ . '.JOB_STALLED',
                                    'Job stalled after {attempts} attempts - please check',
                                    ['attempts' => $stallCount]
                                )
                            );
                            $jobDescriptor->JobStatus = QueuedJob::STATUS_BROKEN;
                        }

                        // now we'll be good and check our memory usage. If it is too high, we'll set the job to
                        // a 'Waiting' state, and let the next processing run pick up the job.
                        if ($this->isMemoryTooHigh()) {
                            $job->addMessage(
                                _t(
                                    __CLASS__ . '.MEMORY_RELEASE',
                                    'Job releasing memory and waiting ({used} used)',
                                    ['used' => $this->humanReadable($this->getMemoryUsage())]
                                )
                            );
                            if ($jobDescriptor->JobStatus != QueuedJob::STATUS_BROKEN) {
                                $jobDescriptor->JobStatus = QueuedJob::STATUS_WAIT;
                            }
                            $broken = true;
                        }

                        // Also check if we are running too long
                        if ($this->hasPassedTimeLimit()) {
                            $job->addMessage(_t(
                                __CLASS__ . '.TIME_LIMIT',
                                'Queue has passed time limit and will restart before continuing'
                            ));
                            if ($jobDescriptor->JobStatus != QueuedJob::STATUS_BROKEN) {
                                $jobDescriptor->JobStatus = QueuedJob::STATUS_WAIT;
                            }
                            $broken = true;
                        }
                    }

                    if ($jobDescriptor) {
                        $this->copyJobToDescriptor($job, $jobDescriptor);
                        $jobDescriptor->write();
                    } else {
                        $this->getLogger()->error(
                            print_r(
                                [
                                    'errno' => 0,
                                    'errstr' => 'Job descriptor has been set to null',
                                    'errfile' => __FILE__,
                                    'errline' => __LINE__,
                                    'errcontext' => [],
                                ],
                                true
                            ),
                            [
                                'file' => __FILE__,
                                'line' => __LINE__,
                            ]
                        );
                        $broken = true;
                    }
                }

                // a last final save. The job is complete by now
                if ($jobDescriptor) {
                    $jobDescriptor->write();
                }

                if ($job->jobFinished()) {
                    /** @var AbstractQueuedJob|QueuedJob $job */
                    $job->afterComplete();
                    $jobDescriptor->cleanupJob();

                    $this->extend('updateJobDescriptorAndJobOnCompletion', $jobDescriptor, $job);
                }
            } catch (Exception $e) {
                // PHP 5.6 exception handling
                $this->handleBrokenJobException($jobDescriptor, $job, $e);
                $broken = true;
            } catch (\Throwable $e) {
                // PHP 7 Error handling)
                $this->handleBrokenJobException($jobDescriptor, $job, $e);
                $broken = true;
            }
        });

        $this->unsetRunAsUser($runAsUser, $originalUser);

        return !$broken;
    }

    /**
     * Provides a wrapper when executing arbitrary code contained in job implementation
     * this ensures that job specific code doesn't alter the configuration of the queue runner execution
     *
     * @param callable $callback
     * @return mixed
     */
    protected function withNestedState(callable $callback)
    {
        // set up a custom error handler for this processing
        $errorHandler = JobErrorHandler::create();

        // Push a config context onto the stack for the duration of this job run.
        Config::nest();

        try {
            return $callback();
        } finally {
            Config::unnest();
            $errorHandler->clear();
        }
    }

    /**
     * @param QueuedJobDescriptor $jobDescriptor
     * @param QueuedJob $job
     * @param Exception|\Throwable $e
     */
    protected function handleBrokenJobException(QueuedJobDescriptor $jobDescriptor, QueuedJob $job, $e)
    {
        // okay, we'll just catch this exception for now
        $this->getLogger()->info(
            $e->getMessage(),
            [
                'exception' => $e,
            ]
        );
        $jobDescriptor->JobStatus =  QueuedJob::STATUS_BROKEN;
        $this->extend('updateJobDescriptorAndJobOnException', $jobDescriptor, $job, $e);
        $jobDescriptor->write();
    }

    /**
     * @param Member $runAsUser
     * @param Member|null $originalUser
     * @return null|Member
     */
    protected function setRunAsUser(Member $runAsUser, Member $originalUser = null)
    {
        // Sanity check. Can't set the user if they don't exist.
        if ($runAsUser === null || !$runAsUser->exists()) {
            return null;
        }

        // Don't need to set Security user if we're already logged in as that same user.
        if ($originalUser && $originalUser->ID === $runAsUser->ID) {
            return null;
        }

        // We are currently either not logged in at all, or we're logged in as a different user. We should switch users
        // so that the context within the Job is correct.
        if (Controller::has_curr()) {
            Security::setCurrentUser($runAsUser);
        } else {
            $_SESSION['loggedInAs'] = $runAsUser->ID;
        }

        // This is an explicit coupling brought about by SS not having a nice way of mocking a user, as it requires
        // session nastiness
        if (class_exists('SecurityContext')) {
            singleton('SecurityContext')->setMember($runAsUser);
        }

        return $runAsUser;
    }

    /**
     * @param Member|null $runAsUser
     * @param Member|null $originalUser
     */
    protected function unsetRunAsUser(Member $runAsUser = null, Member $originalUser = null)
    {
        // No runAsUser was set, so we don't need to do anything.
        if ($runAsUser === null) {
            return;
        }

        // There was no originalUser, so we should make sure that we set the user back to null.
        if (!$originalUser) {
            if (Controller::has_curr()) {
                Security::setCurrentUser(null);
            } else {
                $_SESSION['loggedInAs'] = null;
            }

            return;
        }

        // Okay let's reset our user.
        if (Controller::has_curr()) {
            Security::setCurrentUser($originalUser);
        } else {
            $_SESSION['loggedInAs'] = $originalUser->ID;
        }
    }

    /**
     * Start timer
     */
    protected function markStarted()
    {
        if (!$this->startedAt) {
            $this->startedAt = DBDatetime::now()->getTimestamp();
        }
    }

    /**
     * Is execution time too long?
     *
     * @return bool True if the script has passed the configured time_limit
     */
    protected function hasPassedTimeLimit()
    {
        // Ensure a limit exists
        $limit = static::config()->get('time_limit');
        if (!$limit) {
            return false;
        }

        // Ensure started date is set
        $this->markStarted();

        // Check duration
        $now = DBDatetime::now()->getTimestamp();
        return $now > $this->startedAt + $limit;
    }

    /**
     * Is memory usage too high?
     *
     * @return bool
     */
    protected function isMemoryTooHigh()
    {
        $used = $this->getMemoryUsage();
        $limit = $this->getMemoryLimit();
        return $limit && ($used > $limit);
    }

    /**
     * Get peak memory usage of this application
     *
     * @return float
     */
    protected function getMemoryUsage()
    {
        // Note we use real_usage = false
        // http://stackoverflow.com/questions/15745385/memory-get-peak-usage-with-real-usage
        // Also we use the safer peak memory usage
        return (float)memory_get_peak_usage(false);
    }

    /**
     * Determines the memory limit (in bytes) for this application
     * Limits to the smaller of memory_limit configured via php.ini or silverstripe config
     *
     * @return float Memory limit in bytes
     */
    protected function getMemoryLimit()
    {
        // Limit to smaller of explicit limit or php memory limit
        $limit = $this->parseMemory(static::config()->get('memory_limit'));
        if ($limit) {
            return $limit;
        }

        // Fallback to php memory limit
        $phpLimit = $this->getPHPMemoryLimit();
        if ($phpLimit) {
            return $phpLimit;
        }
    }

    /**
     * Calculate the current memory limit of the server
     *
     * @return float
     */
    protected function getPHPMemoryLimit()
    {
        return $this->parseMemory(trim(ini_get("memory_limit")));
    }

    /**
     * Convert memory limit string to bytes.
     * Based on implementation in install.php5
     *
     * @param string $memString
     *
     * @return float
     */
    protected function parseMemory($memString)
    {
        switch (strtolower(substr($memString, -1))) {
            case "b":
                return round(substr($memString, 0, -1));
            case "k":
                return round(substr($memString, 0, -1) * 1024);
            case "m":
                return round(substr($memString, 0, -1) * 1024 * 1024);
            case "g":
                return round(substr($memString, 0, -1) * 1024 * 1024 * 1024);
            default:
                return round($memString);
        }
    }

    protected function humanReadable($size)
    {
        $filesizename = [" Bytes", " KB", " MB", " GB", " TB", " PB", " EB", " ZB", " YB"];
        return $size ? round($size / pow(1024, ($i = floor(log($size, 1024)))), 2) . $filesizename[$i] : '0 Bytes';
    }


    /**
     * Gets a list of all the current jobs (or jobs that have recently finished)
     *
     * @param string $type
     *          if we're after a particular job list
     * @param int $includeUpUntil
     *          The number of seconds to include jobs that have just finished, allowing a job list to be built that
     *          includes recently finished jobs
     *
     * @return DataList|QueuedJobDescriptor[]
     */
    public function getJobList($type = null, $includeUpUntil = 0)
    {
        return DataObject::get(
            QueuedJobDescriptor::class,
            $this->getJobListFilter($type, $includeUpUntil)
        );
    }

    /**
     * Return the SQL filter used to get the job list - this is used by the UI for displaying the job list...
     *
     * @param string $type
     *          if we're after a particular job list
     * @param int $includeUpUntil
     *          The number of seconds to include jobs that have just finished, allowing a job list to be built that
     *          includes recently finished jobs
     *
     * @return string
     */
    public function getJobListFilter($type = null, $includeUpUntil = 0)
    {
        $util = singleton(QJUtils::class);

        $filter = ['JobStatus <>' => QueuedJob::STATUS_COMPLETE];
        if ($includeUpUntil) {
            $filter['JobFinished > '] = DBDatetime::create()->setValue(
                DBDatetime::now()->getTimestamp() - $includeUpUntil
            )->Rfc2822();
        }

        $filter = $util->dbQuote($filter, ' OR ');

        if ($type) {
            $filter = $util->dbQuote(['JobType =' => (string)$type]) . ' AND (' . $filter . ')';
        }

        return $filter;
    }

    /**
     * Process the job queue with the current queue runner
     *
     * @param string $queue
     */
    public function runQueue($queue)
    {
        if (!self::config()->get('disable_health_check')) {
            $this->checkJobHealth($queue);
        }
        $this->checkdefaultJobs($queue);
        $this->queueRunner->runQueue($queue);
    }

    /**
     * Process all jobs from a given queue
     *
     * @param string $name The job queue to completely process
     */
    public function processJobQueue($name)
    {
        // Start timer to measure lifetime
        $this->markStarted();

        // Begin main loop
        do {
            if (class_exists(Subsite::class)) {
                // clear subsite back to default to prevent any subsite changes from leaking to
                // subsequent actions
                Subsite::changeSubsite(0);
            }

            $job = $this->getNextPendingJob($name);
            if ($job) {
                $success = $this->runJob($job->ID);
                if (!$success) {
                    // make sure job is null so it doesn't continue the current
                    // processing loop. Next queue executor can pick up where
                    // things left off
                    $job = null;
                }
            }
        } while ($job);
    }

    /**
     * When PHP shuts down, we want to process all of the immediate queue items
     *
     * We use the 'getNextPendingJob' method, instead of just iterating the queue, to ensure
     * we ignore paused or stalled jobs.
     */
    public function onShutdown()
    {
        $this->processJobQueue(QueuedJob::IMMEDIATE);
    }

    /**
     * Get a logger
     *
     * @return LoggerInterface
     */
    public function getLogger()
    {
        return Injector::inst()->get(LoggerInterface::class);
    }

    public function enableMaintenanceLock()
    {
        if (!$this->config()->get('lock_file_enabled')) {
            return;
        }

        $path = $this->lockFilePath();
        $contents = DBDatetime::now()->Rfc2822();

        file_put_contents($path, $contents);
    }

    public function disableMaintenanceLock()
    {
        if (!$this->config()->get('lock_file_enabled')) {
            return;
        }

        $path = $this->lockFilePath();
        if (!file_exists($path)) {
            return;
        }

        unlink($path);
    }

    /**
     * @return bool
     */
    public function isMaintenanceLockActive()
    {
        if (!$this->config()->get('lock_file_enabled')) {
            return false;
        }

        $path = $this->lockFilePath();

        return file_exists($path);
    }

    /**
     * Get expiry time for a worker to be operating on a job, helps to identify jobs
     * that have stalled more accurately.
     *
     * @return string
     * @throws Exception
     */
    protected function getWorkerExpiry(): string
    {
        $now = DBDatetime::now()->Rfc2822();
        $time = new DateTime($now);
        $timeToLive = $this->config()->get('worker_ttl');

        if ($timeToLive) {
            $time->add(new DateInterval($timeToLive));
        }

        /** @var DBDatetime $expiry */
        $expiry = DBField::create_field('Datetime', $time->getTimestamp());

        return $expiry->Rfc2822();
    }

    /**
     * @return string
     */
    private function lockFilePath()
    {
        return sprintf(
            '%s%s/%s',
            Director::baseFolder(),
            static::config()->get('lock_file_path'),
            static::config()->get('lock_file_name')
        );
    }
}

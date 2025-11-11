<?php

namespace Symbiote\QueuedJobs\Services;

use SilverStripe\Core\Config\Config;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\Subsites\State\SubsiteState;
use stdClass;
use Symbiote\QueuedJobs\Interfaces\UserContextInterface;

/**
 * A base implementation of a queued job that provides some convenience for implementations
 *
 * This implementation assumes that when you created your job class, you initialised the
 * jobData with relevant variables needed to process() your job later on in execution. If you do not,
 * please ensure you do before you queueJob() the job, to ensure the signature that is generated is 'correct'.
 *
 * @author Marcus Nyeholt <marcus@symbiote.com.au>
 * @license BSD http://silverstripe.org/bsd-license/
 * @skipUpgrade
 */
abstract class AbstractQueuedJob implements QueuedJob, UserContextInterface
{
    /**
     * Number of retry attempts
     * This allows control over how many times a stuck job is retried
     * The value has to be a non-negative integer
     */
    private static int $max_retry_attempts = 0;

    /**
     * The minimum waiting time in seconds between each retry attempt.
     * The value has to be a non-negative integer
     */
    private static int $initial_retry_delay = 0;

    /**
     * Provides the capability to increase the retry period with each retry attempt
     * The value has to be a greater or equal to 1
     */
    private static float $retry_falloff_multiplier = 1;

    /**
     * Acts as a modifier for `retry_falloff_multiplier`
     * It needs to always be a lower value compared to `retry_falloff_multiplier`
     * This allows you to break up clusters of stuck jobs which can prevent load spikes
     * and database deadlocks from forming
     * The value has to be a greater or equal to 0
     */
    private static float $retry_falloff_multiplier_variance = 0;

    /**
     * @var stdClass
     */
    protected $jobData;

    /**
     * @var array
     */
    protected $messages = array();

    /**
     * @var int
     */
    protected $totalSteps = 0;

    /**
     * @var int
     */
    protected $currentStep = 0;

    /**
     * @var boolean
     */
    protected $isComplete = false;

    /**
     * Extensions can have a construct but don't have too.
     * Without a construct, it's impossible to create a job in the CMS
     * @var array params
     */
    public function __construct($params = array())
    {
    }

    /**
     * @return string
     */
    abstract public function getTitle();

    /**
     * Sets a data object for persisting by adding its id and type to the serialised vars
     *
     * @param DataObject $object
     * @param string $name A name to give it, if you want to store more than one
     */
    protected function setObject(DataObject $object, $name = 'Object')
    {
        $this->{$name . 'ID'} = $object->ID;
        $this->{$name . 'Type'} = $object->ClassName;
    }

    /**
     * @param string $name
     * @return DataObject|null
     */
    protected function getObject($name = 'Object')
    {
        $id = $this->{$name . 'ID'};
        $type = $this->{$name . 'Type'};
        if ($id) {
            return DataObject::get($type)->setUseCache(true)->byID($id);
        }
    }

    /**
     * Return a signature for this queued job
     *
     * @return string
     */
    public function getSignature()
    {
        return md5(get_class($this) . serialize($this->jobData));
    }

    /**
     * Generate a somewhat random signature
     *
     * useful if you're want to make sure something is always added
     *
     * @return string
     */
    protected function randomSignature()
    {
        return md5(get_class($this) . DBDatetime::now()->getTimestamp() . mt_rand(0, 100000));
    }

    /**
     * By default jobs should just go into the default processing queue
     *
     * @return string
     */
    public function getJobType()
    {
        return QueuedJob::QUEUED;
    }

    public function getRunAsMemberID()
    {
        return null;
    }

    /**
     * Performs setup tasks the first time this job is run.
     *
     * This is only executed once for every job. If you want to run something on every job restart, use the
     * {@link prepareForRestart} method.
     */
    public function setup()
    {
        $this->loadCustomConfig();
    }

    /**
     * Run when an already setup job is being restarted.
     */
    public function prepareForRestart()
    {
        $this->loadCustomConfig();
    }

    /**
     * Do some processing yourself!
     */
    abstract public function process();

    /**
     * Method for determining whether the job is finished - you may override it if there's
     * more to it than just this
     */
    public function jobFinished()
    {
        return $this->isComplete;
    }

    /**
     * Called when the job is determined to be 'complete'
     */
    public function afterComplete()
    {
    }

    /**
     * @return stdClass
     */
    public function getJobData()
    {
        // okay, we NEED to store the subsite ID if there's one available
        if (!$this->SubsiteID && class_exists(SubsiteState::class)) {
            $this->SubsiteID = SubsiteState::singleton()->getSubsiteId();
        }

        $data = new stdClass();
        $data->totalSteps = $this->totalSteps;
        $data->currentStep = $this->currentStep;
        $data->isComplete = $this->isComplete;
        $data->jobData = $this->jobData;
        $data->messages = $this->messages;

        return $data;
    }

    /**
     * @param int $totalSteps
     * @param int $currentStep
     * @param boolean $isComplete
     * @param stdClass $jobData
     * @param array $messages
     */
    public function setJobData($totalSteps, $currentStep, $isComplete, $jobData, $messages)
    {
        $this->totalSteps = $totalSteps;
        $this->currentStep = $currentStep;
        $this->isComplete = $isComplete;
        $this->jobData = $jobData;
        $this->messages = $messages;
    }

    /**
     * Gets custom config settings to use when running the job.
     *
     * @return array|null
     */
    public function getCustomConfig()
    {
        return $this->CustomConfig;
    }

    /**
     * Sets custom config settings to use when the job is run.
     *
     * @param array $config
     */
    public function setCustomConfig(array $config)
    {
        $this->CustomConfig = $config;
    }

    /**
     * Sets custom configuration settings from the job data.
     */
    private function loadCustomConfig()
    {
        $custom = $this->getCustomConfig();

        if (!is_array($custom)) {
            return;
        }

        foreach ($custom as $class => $settings) {
            foreach ($settings as $setting => $value) {
                Config::modify()->set($class, $setting, $value);
            }
        }
    }

    /**
     * @param string $message
     * @param string $severity
     */
    public function addMessage($message, $severity = 'INFO')
    {
        $severity = strtoupper($severity ?? '');
        $this->messages[] = '[' . DBDatetime::now()->Rfc2822() . "][$severity] $message";
    }

    /**
     * Convenience methods for setting and getting job data
     *
     * @param mixed $name
     * @param mixed $value
     */
    public function __set($name, $value)
    {
        if (!$this->jobData) {
            $this->jobData = new stdClass();
        }
        $this->jobData->$name = $value;
    }

    /**
     * Retrieve some job data
     *
     * @param mixed $name
     * @return mixed
     */
    public function __get($name)
    {
        return isset($this->jobData->$name) ? $this->jobData->$name : null;
    }

    /**
     * Resolves a queue name to one of the queue constants.
     * If $queue is already the value of one of the constants, it will be returned.
     * If the queue is unknown, `null` will be returned.
     */
    public static function getQueue(string|int $queue): ?string
    {
        switch (strtolower($queue)) {
            case 'immediate':
                $queue = QueuedJob::IMMEDIATE;
                break;
            case 'queued':
                $queue = QueuedJob::QUEUED;
                break;
            case 'large':
                $queue = QueuedJob::LARGE;
                break;
            default:
                $queue = (string) $queue;
                $queues = [QueuedJob::IMMEDIATE, QueuedJob::QUEUED, QueuedJob::LARGE];
                if (!ctype_digit($queue) || !in_array($queue, $queues)) {
                    return null;
                }
        }
        return $queue;
    }
}

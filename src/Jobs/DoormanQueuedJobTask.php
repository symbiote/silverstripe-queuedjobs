<?php

namespace Symbiote\QueuedJobs\Jobs;

use AsyncPHP\Doorman\Cancellable;
use AsyncPHP\Doorman\Expires;
use AsyncPHP\Doorman\Process;
use AsyncPHP\Doorman\Task;
use InvalidArgumentException;
use Symbiote\QueuedJobs\DataObjects\QueuedJobDescriptor;
use Symbiote\QueuedJobs\Services\QueuedJob;

class DoormanQueuedJobTask implements Task, Expires, Process, Cancellable
{
    /**
     * @var int
     */
    protected $id;

    /**
     * @var QueuedJobDescriptor|null
     */
    protected $descriptor;

    /**
     * Reload descriptor from DB
     */
    protected function refreshDescriptor()
    {
        if ($this->descriptor) {
            $this->descriptor = QueuedJobDescriptor::get()->byID($this->descriptor->ID);
        }
    }

    /**
     * @inheritdoc
     *
     * @return null|int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @inheritdoc
     *
     * @param int $id
     *
     * @return $this
     */
    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }

    /**
     * @return QueuedJobDescriptor
     */
    public function getDescriptor()
    {
        return $this->descriptor;
    }

    /**
     * @param QueuedJobDescriptor $descriptor
     */
    public function __construct(QueuedJobDescriptor $descriptor)
    {
        $this->descriptor = $descriptor;
    }

    public function __serialize(): array
    {
        return [
            'descriptor' => $this->descriptor->ID,
        ];
    }

    public function __unserialize(array $data): void
    {
        if (!isset($data['descriptor'])) {
            throw new InvalidArgumentException('Malformed data');
        }
        $descriptor = QueuedJobDescriptor::get()
            ->filter('ID', $data['descriptor'])
            ->first();
        if (!$descriptor) {
            throw new InvalidArgumentException('Descriptor not found');
        }
        $this->descriptor = $descriptor;
    }

    /**
     * The __serialize() magic method will be automatically used instead of this
     *
     * @inheritdoc
     *
     * @return string
     * @deprecated will be removed in 5.0
     */
    public function serialize()
    {
        return serialize($this->__serialize);
    }

    /**
     * The __unserialize() magic method will be automatically used instead of this almost all the time
     * This method will be automatically used if existing serialized data was not saved as an associative array
     * and the PHP version used in less than PHP 9.0
     *
     * @inheritdoc
     *
     * @throws InvalidArgumentException
     * @param string
     * @deprecated will be removed in 5.0
     */
    public function unserialize($serialized)
    {
        $data = unserialize($serialized);
        $this->__unserialize($data);
    }

    /**
     * @return string
     */
    public function getHandler()
    {
        return 'DoormanQueuedJobHandler';
    }

    /**
     * @return array
     */
    public function getData()
    {
        return array(
            'descriptor' => $this->descriptor,
        );
    }

    /**
     * @return bool
     */
    public function ignoresRules()
    {
        if ($this->descriptor && $this->descriptor->hasMethod('ignoreRules')) {
            return $this->descriptor->ignoreRules();
        }

        return false;
    }

    /**
     * @return bool
     */
    public function stopsSiblings()
    {
        if ($this->descriptor && $this->descriptor->hasMethod('stopsSiblings')) {
            return $this->descriptor->stopsSiblings();
        }

        return false;
    }

    /**
     * @inheritdoc
     *
     * @return int
     */
    public function getExpiresIn()
    {
        if ($this->descriptor && $this->descriptor->hasMethod('getExpiresIn')) {
            return $this->descriptor->getExpiresIn();
        }

        return -1;
    }

    /**
     * @inheritdoc
     *
     * @param int $startedAt
     * @return bool
     */
    public function shouldExpire($startedAt)
    {
        if ($this->descriptor && $this->descriptor->hasMethod('shouldExpire')) {
            return $this->descriptor->shouldExpire($startedAt);
        }

        return true;
    }

    /**
     * @inheritdoc
     *
     * @return bool
     */
    public function canRunTask()
    {
        $this->refreshDescriptor();

        if ($this->descriptor) {
            return in_array(
                $this->descriptor->JobStatus,
                array(
                    QueuedJob::STATUS_NEW,
                    QueuedJob::STATUS_INIT,
                    QueuedJob::STATUS_WAIT
                )
            );
        }

        return false;
    }

    /**
     * @inheritdoc
     *
     * @return bool
     */
    public function isCancelled()
    {
        $this->refreshDescriptor();

        // Treat completed jobs as cancelled when it comes to how Doorman handles picking up jobs to run
        $cancelledStates = [
            QueuedJob::STATUS_CANCELLED,
            QueuedJob::STATUS_COMPLETE,
        ];

        if ($this->descriptor) {
            return in_array($this->descriptor->JobStatus, $cancelledStates, true);
        }

        return true;
    }
}

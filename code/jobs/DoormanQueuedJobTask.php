<?php

use AsyncPHP\Doorman\Cancellable;
use AsyncPHP\Doorman\Expires;
use AsyncPHP\Doorman\Process;
use AsyncPHP\Doorman\Task;

class DoormanQueuedJobTask implements Task, Expires, Process, Cancellable {
    /**
     * @var int
     */
    protected $id;

    /**
     * @var QueuedJobDescriptor
     */
    protected $descriptor;

    /**
     * Reload descriptor from DB
     */
    protected function refreshDescriptor() {
        if ($this->descriptor) {
            $this->descriptor = QueuedJobDescriptor::get()->byID($this->descriptor->ID);
        }
    }

    /**
     * @inheritdoc
     *
     * @return null|int
     */
    public function getId() {
        return $this->id;
    }

    /**
     * @inheritdoc
     *
     * @param int $id
     *
     * @return $this
     */
    public function setId($id) {
        $this->id = $id;

        return $this;
    }

    /**
     * @return QueuedJobDescriptor
     */
    public function getDescriptor() {
        return $this->descriptor;
    }

    /**
     * @param QueuedJobDescriptor $descriptor
     */
    public function __construct(QueuedJobDescriptor $descriptor) {
        $this->descriptor = $descriptor;
    }

    /**
     * @inheritdoc
     *
     * @return string
     */
    public function serialize() {
        return serialize([
            'descriptor' => $this->descriptor->ID,
        ]);
    }

    /**
     * @inheritdoc
     *
     * @throws InvalidArgumentException
     * @param string
     */
    public function unserialize($serialized) {
        $data = unserialize($serialized);

        if(!isset($data['descriptor'])) {
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
     * @return string
     */
    public function getHandler() {
        return 'DoormanQueuedJobHandler';
    }

    /**
     * @return array
     */
    public function getData() {
        return [
            'descriptor' => $this->descriptor,
        ];
    }

    /**
     * @return bool
     */
    public function ignoresRules() {
        $descriptor = $this->getDescriptor();

        if ($descriptor && $descriptor->hasMethod('ignoreRules')) {
            return $descriptor->ignoreRules();
        }

        return false;
    }

    /**
     * @return bool
     */
    public function stopsSiblings() {
        $descriptor = $this->getDescriptor();

        if ($descriptor && $descriptor->hasMethod('stopsSiblings')) {
            return $descriptor->stopsSiblings();
        }

        return false;
    }

    /**
     * @inheritdoc
     *
     * @return int
     */
    public function getExpiresIn() {
        $descriptor = $this->getDescriptor();

        if ($descriptor && $descriptor->hasMethod('getExpiresIn')) {
            return $descriptor->getExpiresIn();
        }

        return -1;
    }

    /**
     * @inheritdoc
     *
     * @param int $startedAt
     * @return bool
     */
    public function shouldExpire($startedAt) {
        $descriptor = $this->getDescriptor();

        if ($descriptor && $descriptor->hasMethod('shouldExpire')) {
            return $descriptor->shouldExpire($startedAt);
        }

        return true;
    }

    /**
     * @inheritdoc
     *
     * @return bool
     */
    public function canRunTask() {
        $this->refreshDescriptor();

        $descriptor = $this->getDescriptor();

        if ($descriptor) {
            return in_array(
                $descriptor->JobStatus,
                [
                    QueuedJob::STATUS_NEW,
                    QueuedJob::STATUS_INIT,
                    QueuedJob::STATUS_WAIT
                ]
            );
        }

        return false;
    }

    /**
     * @inheritdoc
     *
     * @return bool
     */
    public function isCancelled() {
        $this->refreshDescriptor();

        $descriptor = $this->getDescriptor();

        if ($descriptor) {
            return $this->descriptor->JobStatus === QueuedJob::STATUS_CANCELLED;
        }

        return false;
    }
}

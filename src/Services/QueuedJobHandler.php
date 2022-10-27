<?php

namespace Symbiote\QueuedJobs\Services;

use Monolog\Formatter\FormatterInterface;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\LogRecord;
use SilverStripe\Core\Injector\Injectable;
use Symbiote\QueuedJobs\DataObjects\QueuedJobDescriptor;

/**
 * Writes log output to a job descriptor
 */
class QueuedJobHandler extends AbstractProcessingHandler
{
    use Injectable;

    /** @var QueuedJob */
    protected $job;

    /** @var QueuedJobDescriptor */
    protected $jobDescriptor;

    public function __construct(QueuedJob $job, QueuedJobDescriptor $jobDescriptor)
    {
        parent::__construct();

        $this->job = $job;
        $this->jobDescriptor = $jobDescriptor;
    }

    /**
     * @return QueuedJob
     */
    public function getJob()
    {
        return $this->job;
    }

    /**
     * @return QueuedJobDescriptor
     */
    public function getJobDescriptor()
    {
        return $this->jobDescriptor;
    }

    /**
     * Writes the record down to the log of the implementing handler
     */
    protected function write(LogRecord $record): void
    {
        $this->handleBatch([$record]);
    }

    public function handleBatch(array $records): void
    {
        foreach ($records as $i => $record) {
            $records[$i] = $this->processRecord($records[$i]);
            $records[$i]['formatted'] = $this->getFormatter()->format($records[$i]);
            $this->job->addMessage($records[$i]['formatted'], $records[$i]['level_name'], $records[$i]['datetime']);
        };
        $this->jobDescriptor->SavedJobMessages = serialize($this->job->getJobData()->messages);

        $this->jobDescriptor->write();
    }

    /**
     * Ensure that exception context is retained. Similar logic to SyslogHandler.
     */
    protected function getDefaultFormatter(): FormatterInterface
    {
        return new LineFormatter('%message% %context% %extra%');
    }
}

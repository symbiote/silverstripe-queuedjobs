<?php

namespace App\Queue;

use Psr\Log\LoggerInterface;
use Symbiote\QueuedJobs\Services\QueuedJob;

/**
 * Class Logger
 *
 * This logger redirects all log messages to the queued job which makes the job data contain all relevant logs
 *
 * @package App\Queue
 */
class Logger implements LoggerInterface
{

    /**
     * @var QueuedJob|null
     */
    private $job = null;

    public function setJob(?QueuedJob $job): void
    {
        $this->job = $job;
    }

    public function debug($message, array $context = []): void // phpcs:ignore SlevomatCodingStandard.TypeHints
    {
        $this->logJobMessage($message);
    }

    public function critical($message, array $context = []): void // phpcs:ignore SlevomatCodingStandard.TypeHints
    {
        $this->logJobMessage($message);
    }

    public function alert($message, array $context = []): void // phpcs:ignore SlevomatCodingStandard.TypeHints
    {
        $this->logJobMessage($message);
    }

    public function log($level, $message, array $context = []): void // phpcs:ignore SlevomatCodingStandard.TypeHints
    {
        $this->logJobMessage($message);
    }

    public function emergency($message, array $context = []): void // phpcs:ignore SlevomatCodingStandard.TypeHints
    {
        $this->logJobMessage($message);
    }

    public function warning($message, array $context = []): void // phpcs:ignore SlevomatCodingStandard.TypeHints
    {
        $this->logJobMessage($message);
    }

    public function error($message, array $context = []): void // phpcs:ignore SlevomatCodingStandard.TypeHints
    {
        $this->logJobMessage($message);
    }

    public function notice($message, array $context = []): void // phpcs:ignore SlevomatCodingStandard.TypeHints
    {
        $this->logJobMessage($message);
    }

    public function info($message, array $context = []): void // phpcs:ignore SlevomatCodingStandard.TypeHints
    {
        $this->logJobMessage($message);
    }

    private function logJobMessage(string $message): void
    {
        $job = $this->job;

        if (!$job instanceof QueuedJob) {
            return;
        }

        $job->addMessage($message);
    }
}

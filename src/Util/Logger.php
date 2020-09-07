<?php

namespace Symbiote\QueuedJobs\Util;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symbiote\QueuedJobs\Services\QueuedJob;

/**
 * Class Logger
 *
 * This logger redirects all log messages to the queued job
 * which makes the job data contain all relevant logs
 *
 * @package Symbiote\QueuedJobs\Util
 */
class Logger implements LoggerInterface
{
    /**
     * @var QueuedJob|null
     */
    private $job = null;

    public function setJob(?QueuedJob $job): self
    {
        $this->job = $job;
        return $this;
    }

    public function getJob(): ?QueuedJob
    {
        return $this->job;
    }

    public function debug($message, array $context = []): void
    {
        $this->logJobMessage($message, LogLevel::DEBUG);
    }

    public function critical($message, array $context = []): void
    {
        $this->logJobMessage($message, LogLevel::CRITICAL);
    }

    public function alert($message, array $context = []): void
    {
        $this->logJobMessage($message, LogLevel::ALERT);
    }

    public function emergency($message, array $context = []): void
    {
        $this->logJobMessage($message, LogLevel::EMERGENCY);
    }

    public function warning($message, array $context = []): void
    {
        $this->logJobMessage($message, LogLevel::WARNING);
    }

    public function error($message, array $context = []): void
    {
        $this->logJobMessage($message, LogLevel::ERROR);
    }

    public function notice($message, array $context = []): void
    {
        $this->logJobMessage($message, LogLevel::NOTICE);
    }

    public function info($message, array $context = []): void
    {
        $this->logJobMessage($message, LogLevel::INFO);
    }

    public function log($level, $message, array $context = []): void
    {
        $this->logJobMessage($message, $level, $context);
    }

    private function logJobMessage(string $message, string $level, array $context = []): void
    {
        $job = $this->job;

        if (!$job instanceof QueuedJob) {
            return;
        }

        $job->addMessage($message, $level);
    }
}

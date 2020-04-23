<?php

namespace App\Queue;

trait ExecutionTime
{

    /**
     * Set PHP max execution time
     * Skips setting in tests to avoid timeout issues
     *
     * @param int $seconds
     */
    protected function setMaxExecution(int $seconds): void
    {
        ini_set('max_execution_time', $seconds);
    }

    /**
     * Get max execution time
     *
     * @return int
     */
    protected function getMaxExecution(): int
    {
        return ini_get('max_execution_time');
    }

    /**
     * @param int $executionTime
     * @param callable $callback
     * @return mixed
     */
    protected function withExecutionTime(int $executionTime, callable $callback)
    {
        $originalTime = $this->getMaxExecution();

        try {
            $this->setMaxExecution($executionTime);

            return $callback();
        } finally {
            $this->setMaxExecution($originalTime);
        }
    }
}

<?php

namespace App\Queue\Management;

use SilverStripe\Control\HTTPRequest;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\Queries\SQLSelect;
use SilverStripe\ORM\Queries\SQLUpdate;
use Symbiote\QueuedJobs\DataObjects\QueuedJobDescriptor;
use Symbiote\QueuedJobs\Services\QueuedJob;

/**
 * Class Task
 *
 * Tool for changing job statuses
 *
 * @package App\Queue\Management
 */
class Task extends BuildTask
{

    /**
     * @var string
     */
    private static $segment = 'queue-management-job-status';

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return 'Update job status for specific job type';
    }

    /**
     * @param HTTPRequest $request
     */
    public function run($request): void // phpcs:ignore SlevomatCodingStandard.TypeHints
    {
        /** @var QueuedJobDescriptor $job */
        $job = QueuedJobDescriptor::singleton();
        $statuses = $job->getJobStatusValues();
        sort($statuses, SORT_STRING);

        $currentStatuses = array_diff($statuses, [
            QueuedJob::STATUS_COMPLETE,
        ]);

        // job implementations
        $query = SQLSelect::create(
            'DISTINCT `Implementation`',
            'QueuedJobDescriptor',
            ['`JobStatus` != ?' => QueuedJob::STATUS_COMPLETE],
            ['Implementation' => 'ASC']
        );

        $results = $query->execute();

        $implementations = [];

        // Add job types
        while ($result = $results->next()) {
            $implementation = $result['Implementation'];

            if (!$implementation) {
                continue;
            }

            $implementations[] = $result['Implementation'];
        }

        if (count($implementations) === 0) {
            echo 'No job implementations found.';

            return;
        }

        $implementation = $request->postVar('implementation');
        $currentStatus = $request->postVar('currentStatus');
        $status = $request->postVar('status');

        if ($implementation
            && $status
            && ($implementation === 'all' || in_array($implementation, $implementations))
            && ($currentStatus === 'any' || in_array($currentStatus, $currentStatuses))
            && in_array($status, $statuses)
        ) {
            $where = [
                ['`JobStatus` != ?' => QueuedJob::STATUS_COMPLETE],
            ];

            // Filter by implementation
            $where[] = $implementation === 'all'
                ? '`Implementation` IN ' . sprintf(
                    "('%s')",
                    str_replace('\\', '\\\\', implode("','", $implementations))
                )
                : ['`Implementation`' => $implementation];

            // Filter by status
            if ($currentStatus !== 'any') {
                $where[] = ['`JobStatus`' => $currentStatus];
            }

            // Assemble query
            $query = SQLUpdate::create(
                'QueuedJobDescriptor',
                [
                    'JobStatus' => $status,
                    // make sure to reset all data which is related to job management
                    // job lock
                    'Worker' => null,
                    'Expiry' => null,
                    // resume / pause
                    'ResumeCounts' => 0,
                    // broken job notification
                    'NotifiedBroken' => 0,
                ],
                $where
            );

            $query->execute();

            echo sprintf('Job status updated (%d rows affected).', DB::affected_rows());

            return;
        }

        echo '<form action="" method="post">';

        echo '<p>Job type</p>';

        echo '<select name="implementation">';
        echo '<option value="all">All job types</option>';

        foreach ($implementations as $item) {
            echo sprintf('<option value="%s">%s</option>', $item, $item);
        }

        echo '</select>';

        echo '<br />';

        echo '<p>Current status</p>';

        echo '<select name="currentStatus">';
        echo '<option value="any">Any status (except Complete)</option>';

        foreach ($currentStatuses as $item) {
            echo sprintf('<option value="%s">%s</option>', $item, $item);
        }

        echo '</select>';

        echo '<br />';

        echo '<p>Update status to</p>';

        echo '<select name="status">';

        foreach ($statuses as $item) {
            echo sprintf('<option value="%s">%s</option>', $item, $item);
        }

        echo '</select>';

        echo '<br />';

        echo '<br />';

        echo 'Submitting will apply change immediately:<br><br>';
        echo '<button type="submit">Update status</button>';
        echo '</form>';
    }
}

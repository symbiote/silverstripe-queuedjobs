<?php

namespace App\Queue\Management;

use SilverStripe\Control\HTTPRequest;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\ORM\Queries\SQLSelect;
use Symbiote\QueuedJobs\DataObjects\QueuedJobDescriptor;
use Symbiote\QueuedJobs\Services\QueuedJob;
use Symbiote\QueuedJobs\Services\QueuedJobService;

/**
 * Class Report
 *
 * Queue state overview report
 *
 * @package App\Queue\Management
 */
class Report extends BuildTask
{

    /**
     * {@inheritDoc}
     *
     * @var string
     */
    private static $segment = 'queue-management-report';

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return 'Overall queued jobs completion progress';
    }

    /**
     * @param HTTPRequest $request
     */
    public function run($request): void // phpcs:ignore SlevomatCodingStandard.TypeHints
    {
        $now = DBDatetime::now()->Rfc2822();
        $service = QueuedJobService::singleton();
        $queueState = [];

        if ($service->isMaintenanceLockActive()) {
            $queueState[] = 'Paused';
        }

        if ($service->isAtMaxJobs()) {
            $queueState[] = 'Maximum init jobs';
        }

        $queueState = $queueState
            ? implode(' ', $queueState)
            : 'Running';

        // job states
        $query = SQLSelect::create(
            '`JobStatus`, COUNT(`JobStatus`) as `count`',
            'QueuedJobDescriptor',
            ['StartAfter IS NULL OR StartAfter <= ?' => $now],
            ['count' => 'DESC'],
            ['JobStatus']
        );

        $results = $query->execute();
        $totalJobs = 0;

        $jobsData = [];

        while ($result = $results->next()) {
            $status = $result['JobStatus'];
            $count = $result['count'];
            $jobsData[$status] = $count;
            $totalJobs+= $count;
        }

        $brokenJobs = array_key_exists(QueuedJob::STATUS_BROKEN, $jobsData)
            ? $jobsData[QueuedJob::STATUS_BROKEN]
            : 0;
        $newsJobs = array_key_exists(QueuedJob::STATUS_NEW, $jobsData)
            ? $jobsData[QueuedJob::STATUS_NEW]
            : 0;
        $initJobs = array_key_exists(QueuedJob::STATUS_INIT, $jobsData)
            ? $jobsData[QueuedJob::STATUS_INIT]
            : 0;
        $runningJobs = array_key_exists(QueuedJob::STATUS_RUN, $jobsData)
            ? $jobsData[QueuedJob::STATUS_RUN]
            : 0;
        $completedJobs = array_key_exists(QueuedJob::STATUS_COMPLETE, $jobsData)
            ? $jobsData[QueuedJob::STATUS_COMPLETE]
            : 0;
        $jobsInProgress = $newsJobs + $initJobs + $runningJobs;

        $queueState = $queueState === 'Running' && $jobsInProgress === 0
            ? 'Idle'
            : $queueState;

        // progress bar
        echo sprintf(
            '<h2>[%s] Job progress %0.2f%%</h2>',
            $queueState,
            $totalJobs > 0 ? (($totalJobs - $jobsInProgress) / $totalJobs) * 100 : 0
        );

        $barWidth = 1000;
        echo sprintf(
            '<div style="background-color: white; height: 40px; width: %dpx; border: thin solid black">',
            $barWidth
        );

        foreach (['lime' => $completedJobs, 'red' => $brokenJobs] as $color => $count) {
            echo sprintf(
                '<div title="%d" style="background-color: %s; height: 100%%; width: %0.2fpx; display: inline-block">'
                . '</div>',
                $count,
                $color,
                $totalJobs > 0 ? ($count / $totalJobs) * $barWidth : 0
            );
        }

        echo '</div>';

        echo '<h3>Job status breakdown</h3>';

        foreach ($jobsData as $status => $count) {
            if (!$count) {
                continue;
            }

            echo sprintf('<p><b>%d</b> - %s</p>', $count, $status);
        }

        echo sprintf('<p><b>%d</b> - Total</p>', $totalJobs);

        // first and last completed job
        $query = SQLSelect::create(
            'MAX(`JobFinished`) as `last_job`, MIN(`JobStarted`) as `first_job`',
            'QueuedJobDescriptor',
            [['JobStatus' => QueuedJob::STATUS_COMPLETE]]
        );

        $results = $query->execute();
        $result = $results->first();
        $firstJob = $result['first_job'] ?? '';
        $lastJob = $result['last_job'] ?? '';

        // total job duration
        $query = SQLSelect::create(
            sprintf(
                '`JobTitle`, SUM(UNIX_TIMESTAMP(`JobFinished`) - UNIX_TIMESTAMP(%s) as `duration`, COUNT(*) as `count`',
                'COALESCE(`JobRestarted`, `JobStarted`))'
            ),
            'QueuedJobDescriptor',
            [['JobStatus' => QueuedJob::STATUS_COMPLETE]],
            ['duration' => 'DESC'],
            ['JobTitle']
        );

        $results = $query->execute();

        $totalDuration = 0;
        $jobDurations = [];
        $jobTypesCompleted = [];
        $jobQueueTypeCompleted = [];

        while ($result = $results->next()) {
            $jobType = $result['JobTitle'];
            $duration = $result['duration'];
            $totalDuration += $duration;

            $jobDurations[$jobType] = $duration;

            $count = $result['count'];
            $jobTypesCompleted[$jobType] = $count;
        }

        // total job duration
        $query = SQLSelect::create(
            'JobType, COUNT(*) as `count`',
            'QueuedJobDescriptor',
            [['JobStatus' => QueuedJob::STATUS_COMPLETE]],
            [],
            ['JobType']
        );

        $results = $query->execute();

        while ($result = $results->next()) {
            $jobType = $result['JobType'];
            $count = $result['count'];

            $jobQueueTypeCompleted[$jobType] = $count;
        }

        $elapsed = 0;

        if ($totalDuration > 0) {
            echo sprintf('<p><b>%d</b> s - total job duration</p>', $totalDuration);
            echo sprintf('<p><b>%0.4f</b> s - average job duration</p>', $totalDuration / $completedJobs);
            echo sprintf('<p><b>%s</b> - first job</p>', $firstJob);
            echo sprintf('<p><b>%s</b> - last job</p>', $lastJob);

            $elapsed = strtotime($lastJob) - strtotime($firstJob);
            echo sprintf('<p><b>%s</b> - elapsed time (s)</p>', $elapsed);
        } else {
            echo '<p>No completed jobs found</p>';
        }

        echo '<h3>Durations by job type</h3>';

        foreach ($jobDurations as $jobType => $duration) {
            $jobType = $jobType ?: 'Unknown';

            echo sprintf('<p><b>%d</b> s - %s</p>', $duration, $jobType);
        }

        echo '<h3>Completed jobs by job type</h3>';

        foreach ($jobTypesCompleted as $jobType => $completed) {
            $jobType = $jobType ?: 'Unknown';

            echo sprintf('<p><b>%d</b> jobs - %s</p>', $completed, $jobType);
        }

        echo '<h3>Completed jobs by queue type</h3>';

        $queueTypes = QueuedJobDescriptor::singleton()->getJobTypeValues();

        foreach ($jobQueueTypeCompleted as $jobType => $completed) {
            $jobType = $jobType ?: QueuedJob::QUEUED;

            echo sprintf('<p><b>%d</b> - %s</p>', $completed, $queueTypes[(string) $jobType]);
        }

        echo '<h3>Seconds per completed job by job type</h3>';

        foreach ($jobDurations as $jobType => $duration) {
            $completed = (int) $jobTypesCompleted[$jobType];
            $jobType = $jobType ?: 'Unknown';

            echo sprintf('<p><b>%f</b> s/job - %s</p>', ($duration / $completed), $jobType);
        }

        echo '<h3>Completed jobs per elapsed second by job type</h3>';

        if ($elapsed) {
            foreach ($jobTypesCompleted as $jobType => $completed) {
                $jobType = $jobType ?: 'Unknown';

                echo sprintf('<p><b>%f</b> jobs/elapsed second - %s</p>', ($completed / $elapsed), $jobType);
            }
        }

        // job type breakdown
        $query = SQLSelect::create(
            '`JobTitle`, COUNT(`JobTitle`) as `count`',
            'QueuedJobDescriptor',
            ['StartAfter IS NULL OR StartAfter <= ?' => $now],
            ['count' => 'DESC'],
            ['JobTitle']
        );

        $results = $query->execute();
        echo '<h3>Job type breakdown</h3>';

        while ($result = $results->next()) {
            $count = $result['count'];

            if (!$count) {
                continue;
            }

            echo sprintf('<p><b>%d</b> - %s</p>', $count, $result['JobTitle']);
        }
    }
}

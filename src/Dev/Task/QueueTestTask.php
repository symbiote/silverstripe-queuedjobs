<?php

namespace Symbiote\QueuedJobs\Dev\Task;

use SilverStripe\Core\Validation\ValidationException;
use SilverStripe\Dev\BuildTask;
use SilverStripe\PolyExecution\PolyOutput;
use Symbiote\QueuedJobs\DataObjects\QueuedJobDescriptor;
use Symbiote\QueuedJobs\Dev\Job\QueueTestJob;
use Symbiote\QueuedJobs\Services\QueuedJob;
use Symbiote\QueuedJobs\Services\QueuedJobService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

/**
 * Intended to support local development but also debugging and refining queue settings in general
 */
class QueueTestTask extends BuildTask
{
    protected static string $commandName = 'queue-test-jobs-task';

    protected string $title = 'Generate test jobs task';

    protected static string $description = 'Create test jobs based on the provided specification.';

    public function getOptions(): array
    {
        $options = parent::getOptions();

        $queueTypes = QueuedJobDescriptor::singleton()->getJobTypeValues();

        $options[] = new InputOption(
            'jobs',
            null,
            InputOption::VALUE_OPTIONAL,
            'Total number of jobs to create',
            1
        );

        $options[] = new InputOption(
            'queue',
            null,
            InputOption::VALUE_OPTIONAL,
            'Queue type to select which queue the created jobs will got to',
            QueuedJob::QUEUED,
            array_keys($queueTypes)
        );

        return $options;
    }

    /**
     * @throws ValidationException
     */
    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        $service = QueuedJobService::singleton();
        $total = $input->getOption('jobs');
        $type = $input->getOption('queue');

        $queueMap = QueuedJobDescriptor::singleton()->getJobTypeValues();

        // Sanitise queue type and get human-readable label
        $type = (string) $type;
        $type = array_key_exists($type, $queueMap) ? $type : QueuedJob::QUEUED;
        $queueLabel = $queueMap[$type];

        for ($i = 0; $i < $total; $i += 1) {
            $randomID = $i . date('U');
            $randomID = (int) $randomID;

            $job = new QueueTestJob();
            $job->hydrate($randomID);
            $jobID = $service->queueJob($job, null, null, $type);
            $message = sprintf(
                '"%s" inserted into "%s" queue (job ID = "%d")',
                $job->getTitle(),
                $queueLabel,
                $jobID
            );

            echo $message . PHP_EOL;
        }

        return Command::SUCCESS;
    }
}

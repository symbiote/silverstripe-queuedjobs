<?php

namespace Symbiote\QueuedJobs\Tasks;

use Monolog\Logger;
use SilverStripe\Control\Director;
use SilverStripe\Dev\BuildTask;
use SilverStripe\PolyExecution\PolyOutput;
use SilverStripe\PolyExecution\PolyOutputLogHandler;
use Symbiote\QueuedJobs\Services\AbstractQueuedJob;
use Symbiote\QueuedJobs\Services\QueuedJobService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

/**
 * Task used to process the job queue
 *
 * @author Marcus Nyeholt <marcus@symbiote.com.au>
 * @license BSD http://silverstripe.org/bsd-license/
 */
class ProcessJobQueueTask extends BuildTask
{
    protected static string $commandName = 'ProcessJobQueueTask';

    public static function getDescription(): string
    {
        return _t(
            __CLASS__ . '.Description',
            'Used via a cron job to execute queued jobs that need to be run.'
        );
    }

    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        if (QueuedJobService::singleton()->isMaintenanceLockActive()) {
            return Command::FAILURE;
        }
        $queue = AbstractQueuedJob::getQueue($input->getOption('queue'));
        if ($queue === null) {
            $output->writeln('<error>queue must be one of "immediate", "queued", or "large"</>');
            return Command::INVALID;
        }

        $service = $this->getService();

        // Ensure that log messages are visible when executing this task in CLI.
        // Running the task via browser doesn't need this output because you can check the job in the CMS.
        // Note that if we want to output this to the browser in the future, simply removing this condition
        // isn't enough, because it'll end up double-logging in the job messages tab.
        if (Director::is_cli()) {
            $logger = $service->getLogger();
            if ($logger instanceof Logger) {
                $logger->pushHandler(PolyOutputLogHandler::create($output));
            }
        }

        if ($input->getOption('list')) {
            // List helper
            $service->queueRunner->listJobs();
            return Command::SUCCESS;
        }

        // Check if there is a job to run
        $job = $input->getOption('job');
        if ($job) {
            // Accepts an ID (e.g. "123"), optionally prefixed with anything up to a dash
            // which covers a job cache file name (e.g. "queuedjob-123") and a leading dash (e.g. "-123")
            if (!preg_match('#^(?:.*-)?(\d+)$#', $job, $matches)) {
                $output->writeln('<error>job must be the ID of a job descriptor</>');
                return Command::INVALID;
            }
            $service->runJob((int) $matches[1]);
            return Command::SUCCESS;
        }

        // Run the queue
        $service->runQueue($queue);
        return Command::SUCCESS;
    }

    /**
     * Returns an instance of the QueuedJobService.
     *
     * @return QueuedJobService
     */
    public function getService()
    {
        return QueuedJobService::singleton();
    }

    public function getOptions(): array
    {
        return [
            new InputOption('list', null, InputOption::VALUE_NONE, 'List jobs instead of processing a queue'),
            new InputOption('job', null, InputOption::VALUE_REQUIRED, 'A specific job to run'),
            new InputOption(
                'queue',
                null,
                InputOption::VALUE_REQUIRED,
                'The queue to process',
                'queued',
                ['immediate', 'queued', 'large']
            ),
        ];
    }
}

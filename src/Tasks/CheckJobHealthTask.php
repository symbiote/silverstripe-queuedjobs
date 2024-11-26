<?php

namespace Symbiote\QueuedJobs\Tasks;

use Psr\Log\LoggerInterface;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\BuildTask;
use SilverStripe\PolyExecution\PolyOutput;
use Symbiote\QueuedJobs\Services\AbstractQueuedJob;
use Symbiote\QueuedJobs\Services\QueuedJobService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

class CheckJobHealthTask extends BuildTask
{
    protected static string $commandName = 'CheckJobHealthTask';

    public static function getDescription(): string
    {
        return _t(
            __CLASS__ . '.Description',
            'A task used to check the health of jobs that are "running". Pass a specific queue as the "queue" ' .
            'parameter or otherwise the "Queued" queue will be checked'
        );
    }

    /**
     * Implement this method in the task subclass to
     * execute via the TaskRunner
     */
    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        $queue = AbstractQueuedJob::getQueue($input->getOption('queue'));
        if ($queue === null) {
            $output->writeln('<error>queue must be one of "immediate", "queued", or "large"</>');
            return Command::INVALID;
        }
        $jobHealth = $this->getService()->checkJobHealth($queue);

        $unhealthyJobCount = 0;

        foreach ($jobHealth as $type => $IDs) {
            $count = count($IDs ?? []);
            $output->writeln('Detected and attempted restart on ' . $count . ' ' . $type . ' jobs');
            $unhealthyJobCount = $unhealthyJobCount + $count;
        }

        if ($unhealthyJobCount > 0) {
            $msg = "$unhealthyJobCount jobs are unhealthy";
            /** @var LoggerInterface $logger */
            $Logger = Injector::inst()->get(LoggerInterface::class . '.errorhandler');
            $Logger->error($msg);
            $output->writeln($msg);
            return Command::FAILURE;
        }

        $output->writeln('All jobs are healthy');
        return Command::SUCCESS;
    }

    public function getOptions(): array
    {
        return [
            new InputOption(
                'queue',
                null,
                InputOption::VALUE_REQUIRED,
                'The queue to check',
                'queued',
                ['immediate', 'queued', 'large']
            ),
        ];
    }

    protected function getService()
    {
        return QueuedJobService::singleton();
    }
}

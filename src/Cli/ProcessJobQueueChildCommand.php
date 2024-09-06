<?php

namespace Symbiote\QueuedJobs\Cli;

use Symbiote\QueuedJobs\Services\QueuedJobService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('queuedjobs:process-queue-child', hidden: true)]
class ProcessJobQueueChildCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $task = @unserialize(@base64_decode($input->getArgument('base64-task')));
        if ($task) {
            $this->getService()->runJob($task->getDescriptor()->ID);
        }
        return Command::SUCCESS;
    }

    /**
     * Returns an instance of the QueuedJobService.
     *
     * @return QueuedJobService
     */
    protected function getService()
    {
        return QueuedJobService::singleton();
    }

    protected function configure()
    {
        $this->addArgument('base64-task', InputArgument::REQUIRED);
    }
}

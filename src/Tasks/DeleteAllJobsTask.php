<?php

namespace Symbiote\QueuedJobs\Tasks;

use SilverStripe\Core\Environment;
use SilverStripe\Dev\BuildTask;
use SilverStripe\PolyExecution\PolyOutput;
use SilverStripe\ORM\DataObject;
use Symbiote\QueuedJobs\DataObjects\QueuedJobDescriptor;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

/**
 * An administrative task to delete all queued jobs records from the database.
 * Use with caution!
 */
class DeleteAllJobsTask extends BuildTask
{
    protected static string $commandName = 'delete-queued-jobs';

    public function getTitle(): string
    {
        return "Delete all queued jobs.";
    }

    public static function getDescription(): string
    {
        return "Remove all queued jobs from the database. Use with caution!";
    }

    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        $jobs = DataObject::get(QueuedJobDescriptor::class);

        if (!$input->getOption('confirm')) {
            if (!Environment::isCli()) {
                $confirmText = '?confirm=1 to the URL';
            } else {
                $confirmText = '--confirm';
            }
            $output->writeln('Really delete ' . $jobs->count() . " jobs? Please add $confirmText to confirm.");
            return Command::INVALID;
        }

        $output->writeln('Deleting ' . $jobs->count() . ' jobs...');
        $jobs->removeAll();
        return Command::SUCCESS;
    }

    public function getOptions(): array
    {
        return [
            new InputOption('confirm', null, InputOption::VALUE_NONE, 'Confirm you want to delete the jobs'),
        ];
    }
}

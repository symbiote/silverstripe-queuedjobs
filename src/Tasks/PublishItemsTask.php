<?php

namespace Symbiote\QueuedJobs\Tasks;

use Exception;
use Page;
use SilverStripe\Dev\BuildTask;
use SilverStripe\PolyExecution\PolyOutput;
use Symbiote\QueuedJobs\Jobs\PublishItemsJob;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

/**
 * An example build task that publishes a bunch of pages - this demonstrates a realworld example of how the
 * queued jobs project can be used
 *
 * @author Marcus Nyeholt <marcus@symbiote.com.au>
 * @license BSD http://silverstripe.org/bsd-license/
 */
class PublishItemsTask extends BuildTask
{
    protected static string $commandName = 'PublishItemsTask';

    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        $root = $input->getOption('parent');
        if (!$root) {
            $output->writeln('<error>Sorry, you must provide a parent node to publish from</>');
        }

        $item = Page::get()->setUseCache(true)->byID($root);

        if ($item && $item->exists()) {
            $job = new PublishItemsJob($root);
            singleton('Symbiote\\QueuedJobs\\Services\\QueuedJobService')->queueJob($job);
        }
        return Command::SUCCESS;
    }

    public function getOptions(): array
    {
        return [
            new InputOption(
                'parent',
                null,
                InputOption::VALUE_REQUIRED,
                'The ID of the page you want to publish. This page and its children will be published'
            ),
        ];
    }
}

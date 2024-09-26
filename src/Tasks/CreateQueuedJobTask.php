<?php

namespace Symbiote\QueuedJobs\Tasks;

use Closure;
use ReflectionClass;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Dev\BuildTask;
use SilverStripe\PolyExecution\PolyOutput;
use SilverStripe\ORM\FieldType\DBDatetime;
use Symbiote\QueuedJobs\Services\QueuedJob;
use Symbiote\QueuedJobs\Services\QueuedJobService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

/**
 * A task that can be used to create a queued job.
 *
 * Useful to hook a queued job in to place that needs to exist if it doesn't already.
 *
 * If no name is given, it creates a demo dummy job to help test that things
 * are set up and working
 *
 * @author Marcus Nyeholt <marcus@symbiote.com.au>
 * @license BSD http://silverstripe.org/bsd-license/
 */
class CreateQueuedJobTask extends BuildTask
{
    protected static string $commandName = 'CreateQueuedJobTask';

    public static function getDescription(): string
    {
        return _t(
            __CLASS__ . '.Description',
            'A task used to create a queued job. Pass the queued job class name as the "name" parameter, '
            . 'pass an optional "start" parameter (parseable by strtotime) to set a start time for the job.'
        );
    }

    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        $name = $input->getOption('name');
        if ($name && ClassInfo::exists($name)) {
            $clz = $name;
            $job = new $clz();
        } else {
            $job = new DummyQueuedJob(mt_rand(10, 100));
        }

        $start = $input->getOption('start');
        if ($start) {
            $start = strtotime($start);
            $now = DBDatetime::now()->getTimestamp();
            if ($start >= $now) {
                $friendlyStart = DBDatetime::create()->setValue($start)->Rfc2822();
                $output->writeln('Job queued to start at: <options=bold>' . $friendlyStart . '</>');
                QueuedJobService::singleton()->queueJob($job, $start);
            } else {
                $output->writeln("'start' parameter must be a date/time in the future, parseable with strtotime");
            }
        } else {
            $output->writeln('Job Queued');
            QueuedJobService::singleton()->queueJob($job);
        }
        return Command::SUCCESS;
    }

    public function getOptions(): array
    {
        return [
            new InputOption(
                'name',
                null,
                InputOption::VALUE_REQUIRED,
                'Fully qualified classname for the job to queue',
                suggestedValues: Closure::fromCallable([static::class, 'getAllQueuedJobClasses'])
            ),
            new InputOption(
                'start',
                null,
                InputOption::VALUE_REQUIRED,
                'When to start the job. Must be parsable by '
                . '<href=https://www.php.net/manual/en/function.strtotime.php>strtotime</>'
            ),
        ];
    }

    public static function getAllQueuedJobClasses(): array
    {
        $implementors = ClassInfo::implementorsOf(QueuedJob::class);
        $classes = [];
        foreach ($implementors as $class) {
            $subclasses = ClassInfo::subclassesFor($class);
            foreach ($subclasses as $subclass) {
                $reflectionClass = new ReflectionClass($subclass);
                if ($reflectionClass->isAbstract()) {
                    continue;
                }
                $classes[] = $subclass;
            }
        }
        return $classes;
    }
}

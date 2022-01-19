<?php

namespace Symbiote\QueuedJobs\Tasks;

use SilverStripe\Control\HTTPRequest;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DataObject;
use Symbiote\QueuedJobs\DataObjects\QueuedJobDescriptor;

/**
 * An administrative task to delete all queued jobs records from the database.
 * Use with caution!
 */
class DeleteAllJobsTask extends BuildTask
{
    /**
     * @inheritdoc
     * @return string
     */
    public function getTitle()
    {
        return "Delete all queued jobs.";
    }

    /**
     * @inheritdoc
     * @return string
     */
    public function getDescription()
    {
        return "Remove all queued jobs from the database. Use with caution!";
    }

    /**
     * Run the task
     * @param HTTPRequest $request
     */
    public function run($request)
    {
        $confirm = $request->getVar('confirm');

        $jobs = DataObject::get(QueuedJobDescriptor::class);

        if (!$confirm) {
            echo "Really delete " . $jobs->count() . " jobs? Please add ?confirm=1 to the URL to confirm.";
            return;
        }

        echo "Deleting " . $jobs->count() . " jobs...<br>\n";
        $jobs->removeAll();
        echo "Done.";
    }
}

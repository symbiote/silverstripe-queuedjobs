<?php

namespace Symbiote\QueuedJobs\Tasks;

use Exception;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DataObject;
use Symbiote\QueuedJobs\Jobs\PublishItemsJob;

/**
 * An example build task that publishes a bunch of pages - this demonstrates a realworld example of how the
 * queued jobs project can be used
 *
 * @author Marcus Nyeholt <marcus@symbiote.com.au>
 * @license BSD http://silverstripe.org/bsd-license/
 */
class PublishItemsTask extends BuildTask
{
    /**
     * {@inheritDoc}
     * @var string
     */
    private static $segment = 'PublishItemsTask';

    /**
     * @throws Exception
     * @param HTTPRequest $request
     */
    public function run($request)
    {
        $root = $request->getVar('parent');
        if (!$root) {
            throw new Exception("Sorry, you must provide a parent node to publish from");
        }

        $item = DataObject::get_by_id('Page', $root);

        if ($item && $item->exists()) {
            $job = new PublishItemsJob($root);
            singleton('Symbiote\\QueuedJobs\\Services\\QueuedJobService')->queueJob($job);
        }
    }
}

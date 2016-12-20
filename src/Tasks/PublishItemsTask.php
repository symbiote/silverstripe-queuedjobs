<?php

namespace SilverStripe\QueuedJobs\Tasks;

use Exception;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DataObject;
use SilverStripe\QueuedJobs\Jobs\PublishItemsJob;

/**
 * An example build task that publishes a bunch of pages - this demonstrates a realworld example of how the
 * queued jobs project can be used
 *
 * @author Marcus Nyeholt <marcus@silverstripe.com.au>
 * @license BSD http://silverstripe.org/bsd-license/
 */
class PublishItemsTask extends BuildTask {
	/**
	 * @throws Exception
	 * @param HTTPRequest $request
	 */
	public function run($request) {
		$root = $request->getVar('parent');
		if (!$root) {
			throw new Exception("Sorry, you must provide a parent node to publish from");
		}

		$item = DataObject::get_by_id('Page', $root);

		if ($item && $item->exists()) {
			$job = new PublishItemsJob($root);
			singleton('SilverStripe\\QueuedJobs\\Services\\QueuedJobService')->queueJob($job);
		}
	}
}

<?php

/**
 * An example queued job
 *
 * Use this as an example of how you can write your own jobs
 *
 * @author Marcus Nyeholt <marcus@silverstripe.com.au>
 * @license BSD http://silverstripe.org/bsd-license/
 */
class PublishItemsJob extends AbstractQueuedJob implements QueuedJob {

	public function __construct($rootNodeID = null) {
		// this value is automatically persisted between processing requests for
		// this job
		if ($rootNodeID) {
			$this->rootID = $rootNodeID;
		}
	}

	protected function getRoot() {
		return DataObject::get_by_id('Page', $this->rootID);
	}

	public function getTitle() {
		return "Publish items beneath " . $this->getRoot()->Title;
	}

	/**
	 * Indicate to the system which queue we think we should be in based
	 * on how many objects we're going to touch on while processing.
	 *
	 * We want to make sure we also set how many steps we think we might need to take to
	 * process everything - note that this does not need to be 100% accurate, but it's nice
	 * to give a reasonable approximation
	 *
	 */
	public function getJobType() {

		$this->totalSteps = 'Lots';
		return QueuedJob::QUEUED;
	}

	/**
	 * This is called immediately before a job begins - it gives you a chance
	 * to initialise job data and make sure everything's good to go
	 *
	 * What we're doing in our case is to queue up the list of items we know we need to
	 * process still (it's not everything - just the ones we know at the moment)
	 *
	 * When we go through, we'll constantly add and remove from this queue, meaning
	 * we never overload it with content
	 */
	public function setup() {

		if (!$this->getRoot()) {
			// we're missing for some reason!
			$this->isComplete = true;
			$this->remainingChildren = array();
			return;
		}
		$remainingChildren = array();
		$remainingChildren[] = $this->getRoot()->ID;
		$this->remainingChildren = $remainingChildren;

		// we reset this to 1; this is because we only know for sure about 1 item remaining
		// as time goes by, this will increase as we discover more items that need processing
		$this->totalSteps = 1;
	}

	/**
	 * Lets process a single node, and publish it if necessary
	 */
	public function process() {
		$remainingChildren = $this->remainingChildren;

		// if there's no more, we're done!
		if (!count($remainingChildren)) {
			$this->isComplete = true;
			return;
		}


		// we need to always increment! This is important, because if we don't then our container
		// that executes around us thinks that the job has died, and will stop it running.
		$this->currentStep++;

		// lets process our first item - note that we take it off the list of things left to do
		$ID = array_shift($remainingChildren);

		// get the page
		$page = DataObject::get_by_id('Page', $ID);
		if ($page) {
			// publish it
			$page->doPublish();

			// and add its children to the list to be published
			foreach ($page->Children() as $child) {
				$remainingChildren[] = $child->ID;
				// we increase how many steps we need to do - this means our total steps constantly rises,
				// but it gives users an idea of exactly how many more we know about
				$this->totalSteps++;
			}
			$page->destroy();
			unset($page);
		}

		// and now we store the new list of remaining children
		$this->remainingChildren = $remainingChildren;

		if (!count($remainingChildren)) {
			$this->isComplete = true;
			return;
		}
	}

}
<?php
/*

Copyright (c) 2009, SilverStripe Australia PTY LTD - www.silverstripe.com.au
All rights reserved.

Redistribution and use in source and binary forms, with or without modification, are permitted provided that the following conditions are met:

    * Redistributions of source code must retain the above copyright notice, this list of conditions and the following disclaimer.
    * Redistributions in binary form must reproduce the above copyright notice, this list of conditions and the following disclaimer in the
      documentation and/or other materials provided with the distribution.
    * Neither the name of SilverStripe nor the names of its contributors may be used to endorse or promote products derived from this software
      without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE
LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE
GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT,
STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY
OF SUCH DAMAGE.
*/

/**
 * An example queued job
 *
 * Use this as an example of how you can write your own jobs
 *
 * @author Marcus Nyeholt <marcus@silverstripe.com.au>
 */
class PublishItemsJob extends AbstractQueuedJob implements QueuedJob {
    public function __construct($rootNode) {
		// this value is automatically persisted between processing requests for
		// this job
		$this->rootID = $rootNode->ID;
	}

	protected function getRoot() {
		return DataObject::get_by_id('Page', $this->rootID);
	}

	public function getTitle() {
		return "Publish items beneath ".$this->getRoot()->Title;
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
		$children = $this->getRoot()->Children();
		if (!$children || !$children->Count()) {
			return QueuedJob::QUEUED;
		}

		foreach ($children as $child) {
			$count ++;
			if ($count > 100) {
				$this->totalSteps = '>100';
				return QueuedJob::LARGE;
			}

			$subChildren = $child->Children();
			if ($subChildren) {
				foreach ($subChildren as $sub) {
					$count ++;
					if ($count > 100) {
						$this->totalSteps = '>100';
						return QueuedJob::LARGE;
					}
				}
			}
		}

		$this->totalSteps = $count;
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
		$remainingChildren = array();
		$remainingChildren[] = array($this->getRoot()->ID);
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
		if ($page && $page->Status != 'Published') {
			// publish it
			$page->doPublish();
			$page->destroy();
			unset($page);
			// and add its children to the list to be published
			foreach ($page->Children() as $child) {
				$remainingChildren[] = $child->ID;
				// we increase how many steps we need to do - this means our total steps constantly rises,
				// but it gives users an idea of exactly how many more we know about
				$this->totalSteps++;
			}
		}

		// and now we store the new list of remaining children
		$this->remainingChildren = $remainingChildren;

		if (!count($remainingChildren)) {
			$this->isComplete = true;
			return;
		}
	}
}
?>
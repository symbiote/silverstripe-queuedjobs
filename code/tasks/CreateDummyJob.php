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
 * @author Marcus Nyeholt <marcus@silverstripe.com.au>
 */
class CreateDummyJob extends BuildTask {
    public function run($request) {
		singleton('QueuedJobService')->queueJob(new DummyQueuedJob(mt_rand(10, 100)));
	}
}

class DummyQueuedJob extends AbstractQueuedJob implements QueuedJob {
	public function __construct($number = 0) {
		if ($number) {
			$this->jobData->startNumber = $number;
		}
	}

	public function getTitle() {
		return "Some test job for ".$this->jobData->startNumber.' seconds';
	}

	public function getJobType() {
		return $this->jobData->startNumber > 50 ? QueuedJob::LARGE : QueuedJob::QUEUED;
	}

	public function init() {
		// just demonstrating how to get a job going...
		$this->totalSteps = $this->jobData->startNumber;
		$this->times = array();
	}

	public function process() {
		$times = $this->times;
		// needed due to quirks with __set
		$times[] = date('Y-m-d H:i:s');
		$this->times = $times;

		$this->addMessage("Updated time to " . date('Y-m-d H:i:s'));
		sleep(1);

		// make sure we're incrementing
		$this->currentStep++;

		// and checking whether we're complete
		if ($this->currentStep == $this->totalSteps) {
			$this->isComplete = true;
		}
	}
}
?>
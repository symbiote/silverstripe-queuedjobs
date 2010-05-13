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
 * Task used to process the job queue
 *
 * @author Marcus Nyeholt <marcus@silverstripe.com.au>
 */
class ProcessJobQueueTask extends BuildTask {
    public function run($request) {
		$service = singleton('QueuedJobService');
		/* @var $service QueuedJobService */

		$queue = $request->getVar('queue');
		if (!$queue) {
			$queue = 'Queued';
		}

		switch (strtolower($queue)) {
			case 'immediate': {
				$queue = 1;
				break;
			}
			case 'large': {
				$queue = 3;
				break;
			}
			default: {
				if (!is_numeric($queue)) {
					$queue = 2;
				}
			}
		}

		if ($request->getVar('list')) {
			for ($i = 1; $i  <= 3; $i++) {
				$jobs = $service->getJobList($i);
				$num = $jobs ? $jobs->Count() : 0;
				echo "Found $num jobs for mode $i\n";
			}

			return;
		}

		/* @var $service QueuedJobService */
		$nextJob = $service->getNextPendingJob($queue);

		if ($nextJob) {
			$service->runJob($nextJob->ID);
		}

		if (is_null($nextJob)) {
			echo "No new jobs\n";
		}
		if ($nextJob === false) {
			echo "Job is still running";
		}
	}
}
?>
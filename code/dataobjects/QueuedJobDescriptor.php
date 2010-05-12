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
 * A QueuedJobDescriptor is the stored representation of a piece of work that could take a while to execute,
 * because of which it is desireable to not have it executing in parallel to other jobs.
 *
 * A queued job should always attempt to report how many potential dataobjects will be affected by being executed;
 * this will determine which queue it is placed within so that some shorter jobs can execute immediately without needing
 * to wait for a potentially long running job.
 *
 * @author Marcus Nyeholt <marcus@silverstripe.com.au>
 */
class QueuedJobDescriptor extends DataObject
{
    public static $db = array(
		'JobTitle' => 'Varchar(255)',
		'Implementation' => 'Varchar(64)',
		'StartAfter' => 'SS_Datetime',
		'JobStarted' => 'SS_Datetime',
		'JobFinished' => 'SS_Datetime',
		'TotalSteps' => 'Int',
		'StepsProcessed' => 'Int',
		'SavedJobData' => 'Text',
		'SavedJobMessages' => 'Text',
		'JobStatus' => 'Varchar(16)',
		'JobType' => 'Varchar(16)',
	);

	public static $has_one = array(
		'RunAs' => 'Member',
	);

	public static $defaults = array(
		'JobStatus' => 'New',
	);

	public function pause() {
		if ($this->JobStatus == QueuedJob::STATUS_WAIT || $this->JobStatus == QueuedJob::STATUS_RUN) {
			$this->JobStatus = QueuedJob::STATUS_PAUSED;
			$this->write();
		}
		
	}

	public function resume() {
		if ($this->JobStatus == QueuedJob::STATUS_PAUSED) {
			$this->JobStatus = QueuedJob::STATUS_WAIT;
			$this->write();
		}
	}

	public function getMessages() {
		if (strlen($this->SavedJobMessages)) {
			$msgs = json_decode($this->SavedJobMessages);
			return '<ul><li>'.implode('</li><li>', $msgs).'</li></ul>';
		}
	}
}
?>
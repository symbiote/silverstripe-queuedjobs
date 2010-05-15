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
 * Admin controller for queuedjobs
 *
 * @author Marcus Nyeholt <marcus@silverstripe.com.au>
 */
class QueuedJobsAdmin extends LeftAndMain
{
    static $url_segment = '_queued-jobs';

	static $url_rule = '$Action//$ID';

	static $menu_title = 'Jobs';

	public static $allowed_actions = array(
		'EditForm',
		'showqueue',
	);

	protected $selectedQueue;

	/**
	 * @var QueuedJobsService
	 */
	protected $queuedJobsService;

	public function init() {
		parent::init();

		Requirements::javascript('queuedjobs/javascript/QueuedJobsAdmin.js');

		$qs = singleton('QueuedJobService');
		/* @var $qs QueuedJobService */
		$this->queuedJobsService = $qs;
	}

	public function EditForm() {
		$fields = new FieldSet(new TabSet("Root"));
		$actions = new FieldSet();

		$columns = array(
			'JobTitle' => 'Title',
			'Created' => 'Added',
			'JobStarted' => 'Started',
			'JobStatus' => 'Status',
			'Messages' => 'Messages',
			'StepsProcessed' => 'Number Processed',
			'TotalSteps' => 'Total',
		);

		// QueuedJobListField
		$filter = $this->queuedJobsService->getJobListFilter($this->selectedQueue, 300);
		$table = new QueuedJobListField('QueuedJobs', 'QueuedJobDescriptor', $columns, $filter);

		$table->actions['pause'] = array(
			'label' => 'Pause',
			'icon' => 'queuedjobs/images/control_pause_blue.png',
			'icon_disabled' => 'queuedjobs/images/control_pause_blue.png',
			'class' => 'pauselink'
		);

		$table->actions['resume'] = array(
			'label' => 'Resume',
			'icon' => 'queuedjobs/images/control_play_blue.png',
			'icon_disabled' => 'queuedjobs/images/control_play_blue.png',
			'class' => 'resumelink'
		);

		$table->setPermissions(array('delete', 'pause', 'resume'));

		$fields->addFieldToTab('Root.Main', $table);

		return new Form($this, 'EditForm', $fields, $actions);
	}

	public function showqueue($request) {
		if ($request->param('ID')) {
			$this->selectedQueue = $request->param('ID');
		}
		
		return $this->renderWith('QueuedJobsAdmin_right');
	}
}

?>
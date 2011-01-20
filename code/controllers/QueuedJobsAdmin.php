<?php

/**
 * Admin controller for queuedjobs
 *
 * @author Marcus Nyeholt <marcus@silverstripe.com.au>
 * @license BSD http://silverstripe.org/bsd-license/
 */
class QueuedJobsAdmin extends LeftAndMain
{
    static $url_segment = 'queuedjobs';

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
			'JobTitle' => _t('QueuedJobs.TABLE_TITLE', 'Title'),
			'Created' => _t('QueuedJobs.TABLE_ADDE', 'Added'),
			'JobStarted' => _t('QueuedJobs.TABLE_STARTED', 'Started'),
			'JobRestarted' => _t('QueuedJobs.TABLE_RESUMED', 'Resumed'),
			'StartAfter' => _t('QueuedJobs.TABLE_START_AFTER', 'Start After'),
			'JobStatus' => _t('QueuedJobs.TABLE_STATUS', 'Status'),
			'Messages' => _t('QueuedJobs.TABLE_MESSAGES', 'Message'),
			'StepsProcessed' => _t('QueuedJobs.TABLE_NUM_PROCESSED', 'Number Processed'),
			'TotalSteps' => _t('QueuedJobs.TABLE_TOTAL', 'Total'),
		);

		// QueuedJobListField
		$filter = $this->queuedJobsService->getJobListFilter($this->selectedQueue, 300);
		$table = new QueuedJobListField('QueuedJobs', 'QueuedJobDescriptor', $columns, $filter, 'StartAfter ASC, Created DESC');

		$table->actions['pause'] = array(
			'label' => _t('QueuedJobs.PAUSE_LABEL', 'Pause'),
			'icon' => 'queuedjobs/images/control_pause_blue.png',
			'icon_disabled' => 'queuedjobs/images/control_pause_blue.png',
			'class' => 'pauselink'
		);

		$table->actions['resume'] = array(
			'label' => _t('QueuedJobs.RESUME_LABEL', 'Resume'),
			'icon' => 'queuedjobs/images/control_play_blue.png',
			'icon_disabled' => 'queuedjobs/images/control_play_blue.png',
			'class' => 'resumelink'
		);
		
		$table->actions['execute'] = array(
			'label' => _t('QueuedJobs.EXECUTE_LABEL', 'Execute'),
			'icon' => 'queuedjobs/images/cog_go.png',
			'icon_disabled' => 'queuedjobs/images/cog_go.png',
			'class' => 'executelink'
		);

		$table->setPermissions(array('delete', 'pause', 'resume', 'execute'));

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

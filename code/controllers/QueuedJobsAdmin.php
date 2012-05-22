<?php

/**
 * @author Marcus Nyeholt <marcus@silverstripe.com.au>
 * @license BSD http://silverstripe.org/bsd-license/
 */
class QueuedJobsAdmin extends ModelAdmin {
    static $url_segment = 'queuedjobs';
	static $menu_title = 'Jobs';
	
	static $managed_models = array('QueuedJobDescriptor');

	public static $dependencies = array(
		'jobQueue'			=> '%$QueuedJobService',
	);
	
	/**
	 *
	 * @var QueuedJobService
	 */
	public $jobQueue;
	
	public function EditForm($request = null) {
		$form = parent::EditForm($request);
		
		$filter = $this->jobQueue->getJobListFilter();

		$list = DataList::create('QueuedJobDescriptor');
		$list->where($filter);
		
		$grid = new GridField('QueuedJobDescriptor', 'Jobs', $list);
		$grid->setForm($form);
		$form->Fields()->replaceField('QueuedJobDescriptor', $grid);
		
		$grid->getConfig()->addComponent(new GridFieldQueuedJobExecute());
		$grid->getConfig()->addComponent(new GridFieldDeleteAction());
		
		return $form;
		
		$columns = array(
			'JobTitle' => _t('QueuedJobs.TABLE_TITLE', 'Title'),
			'Created' => _t('QueuedJobs.TABLE_ADDE', 'Added'),
			'JobStarted' => _t('QueuedJobs.TABLE_STARTED', 'Started'),
			'JobRestarted' => _t('QueuedJobs.TABLE_RESUMED', 'Resumed'),
			'StartAfter' => _t('QueuedJobs.TABLE_START_AFTER', 'Start After'),
			'JobType'	=> _t('QueuedJobs.JOB_TYPE', 'Job Type'),
			'JobStatus' => _t('QueuedJobs.TABLE_STATUS', 'Status'),
			'Messages' => _t('QueuedJobs.TABLE_MESSAGES', 'Message'),
			'StepsProcessed' => _t('QueuedJobs.TABLE_NUM_PROCESSED', 'Number Processed'),
			'TotalSteps' => _t('QueuedJobs.TABLE_TOTAL', 'Total'),
		);

		// QueuedJobListField
		
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
	
	public function Tools() {
		return '';
	}
}

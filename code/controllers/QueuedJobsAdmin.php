<?php

/**
 * @author Marcus Nyeholt <marcus@silverstripe.com.au>
 * @license BSD http://silverstripe.org/bsd-license/
 */
class QueuedJobsAdmin extends ModelAdmin {
    private static $url_segment = 'queuedjobs';
	private static $menu_title = 'Jobs';
	private static $menu_icon = "queuedjobs/images/clipboard.png";
	
	private static $managed_models = array('QueuedJobDescriptor');

	private static $dependencies = array(
		'jobQueue'			=> '%$QueuedJobService',
	);
	
	private static $allowed_actions = array(
		'EditForm'
	);

	/**
	 * @var QueuedJobService
	 */
	public $jobQueue;
	
	public function getEditForm($id = null, $fields = null) {
		$form = parent::getEditForm($id, $fields);
		
		$filter = $this->jobQueue->getJobListFilter(null, 300);

		$list = DataList::create('QueuedJobDescriptor');
		$list = $list->where($filter);
		
		$grid = new GridField(
			'QueuedJobDescriptor', 
			_t('QueuedJobs.JobsFieldTitle','Jobs'), 
			$list
		);
		$grid->setForm($form);
		
		$form->Fields()->replaceField('QueuedJobDescriptor', $grid);
		
		$grid->getConfig()->addComponent(new GridFieldQueuedJobExecute());
		$grid->getConfig()->addComponent(new GridFieldQueuedJobExecute('pause', function ($record) {
			return $record->JobStatus == QueuedJob::STATUS_WAIT || $record->JobStatus == QueuedJob::STATUS_RUN;
		}));
		$grid->getConfig()->addComponent(new GridFieldQueuedJobExecute('resume', function ($record) {
			return $record->JobStatus == QueuedJob::STATUS_PAUSED || $record->JobStatus == QueuedJob::STATUS_BROKEN;
		}));
		$grid->getConfig()->addComponent(new GridFieldDeleteAction());
		
		$formatting = array(
			'Messages'		=> function ($val, $obj) {
				return "<div style='max-width: 300px; max-height: 200px; overflow: auto;'>$obj->Messages</div>";
			},
		);
			
		$grid->getConfig()->getComponentByType('GridFieldDataColumns')->setFieldFormatting($formatting);
		
		return $form;
	}
	
	public function Tools() {
		return '';
	}
}

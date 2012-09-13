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
		
		$filter = $this->jobQueue->getJobListFilter(null, 300);

		$list = DataList::create('QueuedJobDescriptor');
		$list->where($filter);
		
		$grid = new GridField('QueuedJobDescriptor', 'Jobs', $list);
		$grid->setForm($form);
		$form->Fields()->replaceField('QueuedJobDescriptor', $grid);
		
		$grid->getConfig()->addComponent(new GridFieldQueuedJobExecute());
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

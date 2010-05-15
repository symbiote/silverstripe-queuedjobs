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

class QueuedJobListField extends TableListField {
	function __construct($name, $sourceClass, $fieldList = null, $sourceFilter = null,
		$sourceSort = null, $sourceJoin = null) {
		parent::__construct($name, $sourceClass, $fieldList, $sourceFilter, $sourceSort, $sourceJoin);
		$this->itemClass = 'QueuedJobListField_Item';
	}

	function handleItem($request) {
		return new QueuedJobListField_ItemRequest($this, $request->param('ID'));
	}

	function FieldHolder() {
		Requirements::javascript('queuedjobs/javascript/QueuedJobListField.js');
		return parent::FieldHolder();
	}
}

/**
 * @author Marcus Nyeholt <marcus@silverstripe.com.au>
 */
class QueuedJobListField_Item extends TableListField_Item
{
	function PauseLink() {
		return Controller::join_links($this->Link(), "pause");
	}

	function ResumeLink() {
		return Controller::join_links($this->Link(), "resume");
	}

	/**
	 * Returns all row-based actions not disallowed through permissions.
	 * See TableListField->Action for a similiar dummy-function to work
	 * around template-inheritance issues.
	 *
	 * @return DataObjectSet
	 */
	function Actions() {
		$allowedActions = new DataObjectSet();
		foreach($this->parent->actions as $actionName => $actionSettings) {
			$can = true;
			switch ($actionName) {
				case 'pause': {
					if ($this->item->JobStatus != QueuedJob::STATUS_RUN && $this->item->JobStatus != QueuedJob::STATUS_WAIT) {
						$can = false;
					}
					break;
				}
				case 'resume': {
					if ($this->item->JobStatus != QueuedJob::STATUS_PAUSED && $this->item->JobStatus != QueuedJob::STATUS_BROKEN) {
						$can = false;
					}
					break;
				}
				case 'delete': {
					if (!($this->item->JobStatus == QueuedJob::STATUS_NEW || $this->item->JobStatus == QueuedJob::STATUS_BROKEN)) {
						$can = false;
					}
					break;
				}
			}

			if($can && $this->parent->Can($actionName)) {
				$allowedActions->push(new ArrayData(array(
					'Name' => $actionName,
					'Link' => $this->{ucfirst($actionName).'Link'}(),
					'Icon' => $actionSettings['icon'],
					'IconDisabled' => $actionSettings['icon_disabled'],
					'Label' => $actionSettings['label'],
					'Class' => $actionSettings['class'],
					'Default' => ($actionName == $this->parent->defaultAction),
					'IsAllowed' => $this->Can($actionName),
				)));
			}
		}

		return $allowedActions;
	}
}

class QueuedJobListField_ItemRequest extends TableListField_ItemRequest {
	public function pause() {
		$this->dataObj()->pause();
	}

	public function resume() {
		$this->dataObj()->resume();
	}
}
?>
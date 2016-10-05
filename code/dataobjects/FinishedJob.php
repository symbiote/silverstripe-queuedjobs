<?php

/**
 * Created by IntelliJ IDEA.
 * User: simon
 * Date: 18/09/16
 * Time: 17:05
 */
class FinishedJob extends QueuedJobDescriptor
{

    public function canCreate($member = null)
    {
        return false;
    }

    public function canDelete($member = null)
    {
        return true;
    }

    public function canEdit($member = null)
    {
        return false;
    }

    public function canView($member = null)
    {
        return true;
    }

    public function getCMSFields() {
        $fields = parent::getCMSFields();
        $fields->addFieldToTab('Root.Main', LiteralField::create('Messages', $this->getMessages()));
        return $fields;
    }
}

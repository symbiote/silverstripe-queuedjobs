<?php

namespace App\Queue\Admin;

use SilverStripe\Core\Extension as BaseExtension;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig;
use SilverStripe\Forms\GridField\GridFieldDataColumns;
use SilverStripe\Forms\GridField\GridFieldFilterHeader;
use SilverStripe\Forms\GridField\GridFieldPaginator;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\FieldType\DBDatetime;
use Symbiote\QueuedJobs\Controllers\QueuedJobsAdmin;
use Symbiote\QueuedJobs\DataObjects\QueuedJobDescriptor;
use Terraformers\RichFilterHeader\Form\GridField\RichFilterHeader;

/**
 * Class Extension
 *
 * @property QueuedJobsAdmin|$this $owner
 * @package App\Queue\Admin
 */
class Extension extends BaseExtension
{

    private const SCHEDULED_FILTER_FUTURE = 'future';
    private const SCHEDULED_FILTER_PAST = 'past';

    /**
     * @param Form $form
     */
    public function updateEditForm(Form $form): void
    {
        $fields = $form->Fields();

        // there are multiple fields that need to be updated
        $fieldNames = [
            'QueuedJobDescriptor',
            $this->encodeClassName(QueuedJobDescriptor::class),
        ];

        foreach ($fieldNames as $fieldName) {
            /** @var GridField $gridField */
            $gridField = $fields->fieldByName($fieldName);

            if (!$gridField) {
                continue;
            }

            $config = $gridField->getConfig();

            // apply custom filters
            $this->customiseFilters($config);
        }
    }

    /**
     * Customise queued jobs filters UI
     *
     * @param GridFieldConfig $config
     */
    private function customiseFilters(GridFieldConfig $config): void
    {
        /** @var GridFieldDataColumns $gridFieldColumns */
        $gridFieldColumns = $config->getComponentByType(GridFieldDataColumns::class);

        $gridFieldColumns->setDisplayFields([
            'getImplementationSummary' => 'Type',
            'JobTypeString' => 'Queue',
            'JobStatus' => 'Status',
            'JobTitle' => 'Description',
            'Created' => 'Added',
            'StartAfter' => 'Scheduled',
            'JobFinished' => 'Finished',
        ]);

        $config->removeComponentsByType(GridFieldFilterHeader::class);

        $filter = new RichFilterHeader();
        $filter
            ->setFilterConfig([
                'getImplementationSummary' => 'Implementation',
                'Description' => 'JobTitle',
                'Status' => [
                    'title' => 'JobStatus',
                    'filter' => 'ExactMatchFilter',
                ],
                'JobTypeString' => [
                    'title' => 'JobType',
                    'filter' => 'ExactMatchFilter',
                ],
                'Created' => 'Added',
                'StartAfter' => 'Scheduled',
            ])
            ->setFilterFields([
                'JobType' => $queueType = DropdownField::create(
                    '',
                    '',
                    $this->getQueueTypes()
                ),
                'JobStatus' => $jobStatus = DropdownField::create(
                    '',
                    '',
                    $this->getJobStatuses()
                ),
                'Added' => $added = DropdownField::create(
                    '',
                    '',
                    $this->getAddedDates()
                ),
                'Scheduled' => $scheduled = DropdownField::create(
                    '',
                    '',
                    [
                        self::SCHEDULED_FILTER_FUTURE => self::SCHEDULED_FILTER_FUTURE,
                        self::SCHEDULED_FILTER_PAST => self::SCHEDULED_FILTER_PAST,
                    ]
                ),
            ])
            ->setFilterMethods([
                'Added' => static function (DataList $list, $name, $value): DataList {
                    if ($value) {
                        $added = DBDatetime::now()->modify($value);

                        return $list->filter(['Created:LessThanOrEqual' => $added->Rfc2822()]);
                    }

                    return $list;
                },
                'Scheduled' => static function (DataList $list, $name, $value): DataList {
                    if ($value === static::SCHEDULED_FILTER_FUTURE) {
                        return $list->filter([
                            'StartAfter:GreaterThan' => DBDatetime::now()->Rfc2822(),
                        ]);
                    }

                    if ($value === static::SCHEDULED_FILTER_PAST) {
                        return $list->filter([
                            'StartAfter:LessThanOrEqual' => DBDatetime::now()->Rfc2822(),
                        ]);
                    }

                    return $list;
                },
            ]);

        foreach ([$jobStatus, $queueType, $added, $scheduled] as $dropDownField) {
            /** @var DropdownField $dropDownField */
            $dropDownField->setEmptyString('-- select --');
        }

        $config->addComponent($filter, GridFieldPaginator::class);
    }

    /**
     * Queue types options for drop down field
     *
     * @return array
     */
    private function getQueueTypes(): array
    {
        /** @var QueuedJobDescriptor $job */
        $job = QueuedJobDescriptor::singleton();
        $map = $job->getJobTypeValues();
        $values = array_values($map);
        $keys = [];

        foreach (array_keys($map) as $key) {
            $keys[] = (int) $key;
        }

        return array_combine($keys, $values);
    }

    /**
     * All possible job statuses (this list is not exposed by the module)
     * intended to be used in a drop down field
     *
     * @return array
     */
    private function getJobStatuses(): array
    {
        /** @var QueuedJobDescriptor $job */
        $job = QueuedJobDescriptor::singleton();
        $statuses = $job->getJobStatusValues();

        sort($statuses, SORT_STRING);

        $statuses = array_combine($statuses, $statuses);

        return $statuses;
    }

    /**
     * Encode class name to match the matching CMS field name
     *
     * @param string $className
     * @return string
     */
    private function encodeClassName(string $className): string
    {
        return str_replace('\\', '-', $className);
    }

    /**
     * Date options for added dates drop down field
     *
     * @return array
     */
    private function getAddedDates(): array
    {
        return [
            '-1 day' => '1 day or older',
            '-3 day' => '3 days or older',
            '-7 day' => '7 days or older',
            '-14 day' => '14 days or older',
            '-1 month' => '1 month or older',
        ];
    }
}

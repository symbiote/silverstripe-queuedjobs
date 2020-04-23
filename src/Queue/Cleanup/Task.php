<?php

namespace App\Queue\Cleanup;

use SilverStripe\Control\HTTPRequest;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\FieldType\DBDatetime;
use Symbiote\QueuedJobs\DataObjects\QueuedJobDescriptor;
use Symbiote\QueuedJobs\Services\QueuedJob;
use Symbiote\QueuedJobs\Services\QueuedJobService;

/**
 * Class Task
 *
 * Delete all expired completed jobs
 *
 * @package App\Queue\Cleanup
 */
class Task extends BuildTask
{

    private const EXPIRY_HOURS = 24;
    private const EXPIRY_LIMIT = 10000;

    /**
     * @var string
     */
    private static $segment = 'queued-jobs-cleanup';

    /**
     * @var string
     */
    protected $description = 'Delete job descriptors that are older than the configured expiry';

    /**
     * @param HTTPRequest $request
     */
    public function run($request): void // phpcs:ignore SlevomatCodingStandard.TypeHints
    {
        if (QueuedJobService::singleton()->isMaintenanceLockActive()) {
            return;
        }

        $table = QueuedJobDescriptor::config()->get('table_name');

        // determine expiry
        $expired = DBDatetime::now()->modify(sprintf('-%s hours', self::EXPIRY_HOURS))->Rfc2822();

        // Format query
        $query = sprintf(
            "DELETE FROM `%s` WHERE `JobStatus` = '%s' AND (`JobFinished` <= '%s' OR `JobFinished` IS NULL) LIMIT %d",
            $table,
            QueuedJob::STATUS_COMPLETE,
            $expired,
            self::EXPIRY_LIMIT
        );

        DB::query($query);

        echo sprintf('%d job descriptors deleted.', (int) DB::affected_rows());
    }
}

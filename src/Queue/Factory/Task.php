<?php

namespace App\Queue\Factory;

use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\ValidationException;
use Symbiote\QueuedJobs\Services\QueuedJobService;

/**
 * Class Task
 *
 * generic task which creates jobs from id list
 * each job contains a chunk of the ids
 * GET param options
 * size - (int) chunk size
 * limit - (int) limits the number of IDs processed, test only, defaults to 0 (no limit)
 * offset - (int) apply offset when using limit
 * jobs - (int) number of chunks per factory job, setting it 0 skips factory jobs step
 * used for long execution runs where more than 3000 specified jobs are created
 *
 * @package App\Queue\Factory
 */
abstract class Task extends BuildTask
{

    private const FACTORY_JOBS_BATCH_SIZE = 500;

    /**
     * @param HTTPRequest $request
     * @param DataList $list
     * @param string $jobClass
     * @param int $size
     * @throws ValidationException
     */
    protected function queueJobsFromList(HTTPRequest $request, DataList $list, string $jobClass, int $size): void
    {
        $limit = (int) $request->getVar('limit');
        $offset = (int) $request->getVar('offset');

        if ($limit > 0) {
            $list = $list->limit($limit, $offset);
        }

        $ids = $list->columnUnique('ID');
        $this->queueJobsFromIds($request, $ids, $jobClass, $size);
    }

    /**
     * @param HTTPRequest $request
     * @param array $ids
     * @param string $jobClass
     * @param int $size
     * @throws ValidationException
     */
    protected function queueJobsFromIds(HTTPRequest $request, array $ids, string $jobClass, int $size): void
    {
        $ids = $this->formatIds($ids);
        $this->queueJobsFromData($request, $ids, $jobClass, $size);
    }

    /**
     * @param HTTPRequest $request
     * @param array $data
     * @param string $jobClass
     * @param int $size
     * @throws ValidationException
     */
    protected function queueJobsFromData(HTTPRequest $request, array $data, string $jobClass, int $size): void
    {
        if (count($data) === 0) {
            return;
        }

        $jobs = $request->getVar('jobs') ?? self::FACTORY_JOBS_BATCH_SIZE;
        $jobs = (int) $jobs;

        $chunkSize = (int) $request->getVar('size');
        $chunkSize = $chunkSize > 0
            ? $chunkSize
            : $size;

        $chunks = array_chunk($data, $chunkSize);

        if ($jobs > 0) {
            $this->createFactoryJobs($chunks, $jobClass, $jobs);

            return;
        }

        $this->createSpecifiedJobs($chunks, $jobClass);
    }

    /**
     * @param array $chunks
     * @param string $jobClass
     * @throws ValidationException
     */
    private function createSpecifiedJobs(array $chunks, string $jobClass): void
    {
        $service = QueuedJobService::singleton();

        foreach ($chunks as $chunk) {
            $job = Injector::inst()->create($jobClass);
            $job->hydrate(array_values($chunk));
            $service->queueJob($job);
        }
    }

    /**
     * @param array $chunks
     * @param string $jobClass
     * @param int $chunkSize
     * @throws ValidationException
     */
    private function createFactoryJobs(array $chunks, string $jobClass, int $chunkSize): void
    {
        $service = QueuedJobService::singleton();
        $chunks = array_chunk($chunks, $chunkSize);

        foreach ($chunks as $chunk) {
            $job = new Job();
            $job->hydrate($jobClass, array_values($chunk));
            $service->queueJob($job);
        }
    }

    /**
     * Cast all IDs to int so we don't end up with type errors
     *
     * @param array $ids
     * @return array
     */
    private function formatIds(array $ids): array
    {
        $formatted = [];

        foreach ($ids as $id) {
            $formatted[] = (int) $id;
        }

        return $formatted;
    }
}

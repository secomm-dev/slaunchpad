<?php
/**
 * Mirasvit
 *
 * This source file is subject to the Mirasvit Software License, which is available at https://mirasvit.com/license/.
 * Do not edit or add to this file if you wish to upgrade the to newer versions in the future.
 * If you wish to customize this module for your needs.
 * Please refer to http://www.magentocommerce.com for more information.
 *
 * @category  Mirasvit
 * @package   mirasvit/module-seo
 * @version   2.12.8
 * @copyright Copyright (C) 2026 Mirasvit (https://mirasvit.com/)
 */


declare(strict_types=1);

namespace Mirasvit\SeoAudit\Repository;

use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\DataObject;
use Magento\Framework\EntityManager\EntityManager;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Mirasvit\SeoAudit\Api\Data\JobInterface;
use Mirasvit\SeoAudit\Api\Data\JobSearchResultsInterface;
use Mirasvit\SeoAudit\Api\Data\JobSearchResultsInterfaceFactory;
use Mirasvit\SeoAudit\Api\JobRepositoryInterface;
use Mirasvit\SeoAudit\Model\JobFactory;
use Mirasvit\SeoAudit\Model\ResourceModel\Job\Collection;
use Mirasvit\SeoAudit\Model\ResourceModel\Job\CollectionFactory;

class JobRepository implements JobRepositoryInterface
{
    private $jobFactory;

    private $collectionFactory;

    private $entityManager;

    private $collectionProcessor;

    private $searchResultsFactory;

    public function __construct(
        JobFactory                       $jobFactory,
        CollectionFactory                $collectionFactory,
        EntityManager                    $entityManager,
        CollectionProcessorInterface     $collectionProcessor,
        JobSearchResultsInterfaceFactory $searchResultsFactory
    ) {
        $this->jobFactory           = $jobFactory;
        $this->collectionFactory    = $collectionFactory;
        $this->entityManager        = $entityManager;
        $this->collectionProcessor  = $collectionProcessor;
        $this->searchResultsFactory = $searchResultsFactory;
    }

    public function getCollection(): Collection
    {
        return $this->collectionFactory->create();
    }

    public function create(): JobInterface
    {
        return $this->jobFactory->create();
    }

    public function get(int $id): ?JobInterface
    {
        $job = $this->create();
        $job = $this->entityManager->load($job, $id);

        return $job->getId() ? $job : null;
    }

    public function getRunningJob(): ?JobInterface
    {
        /** @var JobInterface $runningJob */
        $runningJob = $this->getCollection()
            ->addFieldToFilter(JobInterface::STATUS, JobInterface::STATUS_PROCESSING)
            ->getLastItem();

        return $runningJob->getId() ? $runningJob : null;
    }

    public function delete(JobInterface $job): bool
    {
        $this->entityManager->delete($job);

        return true;
    }

    /**
     * @inheritdoc
     */
    public function getById(int $jobId): JobInterface
    {
        $job = $this->create();
        $this->entityManager->load($job, $jobId);

        if (!$job->getId()) {
            throw new NoSuchEntityException(
                __('Job with ID "%1" does not exist.', $jobId)
            );
        }

        return $job;
    }

    /**
     * @inheritdoc
     */
    public function getList(SearchCriteriaInterface $searchCriteria): JobSearchResultsInterface
    {
        $collection = $this->collectionFactory->create();
        $this->collectionProcessor->process($searchCriteria, $collection);

        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setSearchCriteria($searchCriteria);
        $searchResults->setItems($collection->getItems());
        $searchResults->setTotalCount($collection->getSize());

        return $searchResults;
    }

    /**
     * @inheritdoc
     */
    public function save(JobInterface $job, ?int $jobId = null): JobInterface
    {
        $id = $jobId ?? ($job->getId() ? (int)$job->getId() : null);

        if ($id !== null) {
            $model = $this->getById($id);
        } else {
            $model = $this->create();
        }

        $this->mergeIncoming($model, $job);

        try {
            $this->entityManager->save($model);
        } catch (\Exception $e) {
            throw new CouldNotSaveException(
                __('Could not save job: %1', $e->getMessage())
            );
        }

        return $model;
    }

    /**
     * @inheritdoc
     */
    public function deleteById(int $jobId): bool
    {
        $model = $this->getById($jobId);

        try {
            $this->entityManager->delete($model);
        } catch (\Exception $e) {
            throw new CouldNotDeleteException(
                __('Could not delete job with ID "%1": %2', $jobId, $e->getMessage())
            );
        }

        return true;
    }

    private function mergeIncoming(JobInterface $model, JobInterface $incoming): void
    {
        /** @var DataObject $incoming */

        if ($incoming->hasData(JobInterface::STATUS)) {
            $model->setStatus($incoming->getStatus());
        }
        if ($incoming->hasData(JobInterface::MESSAGE)) {
            $model->setMessage($incoming->getMessage());
        }
        if ($incoming->hasData(JobInterface::STARTED_AT)) {
            $model->setStartedAt($incoming->getStartedAt());
        }
        if ($incoming->hasData(JobInterface::FINISHED_AT)) {
            $model->setFinishedAt($incoming->getFinishedAt());
        }
        if ($incoming->hasData(JobInterface::RESULT_SERIALIZED)) {
            $model->setData(JobInterface::RESULT_SERIALIZED, $incoming->getData(JobInterface::RESULT_SERIALIZED));
        }
    }
}

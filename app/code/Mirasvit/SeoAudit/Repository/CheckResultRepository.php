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
use Magento\Framework\EntityManager\EntityManager;
use Magento\Framework\Exception\NoSuchEntityException;
use Mirasvit\SeoAudit\Api\CheckResultRepositoryInterface;
use Mirasvit\SeoAudit\Api\Data\CheckResultInterface;
use Mirasvit\SeoAudit\Api\Data\CheckResultSearchResultsInterface;
use Mirasvit\SeoAudit\Api\Data\CheckResultSearchResultsInterfaceFactory;
use Mirasvit\SeoAudit\Api\Data\UrlInterface;
use Mirasvit\SeoAudit\Check\AbstractCheck;
use Mirasvit\SeoAudit\Model\CheckResultFactory;
use Mirasvit\SeoAudit\Model\ResourceModel\CheckResult\Collection;
use Mirasvit\SeoAudit\Model\ResourceModel\CheckResult\CollectionFactory;
use Mirasvit\SeoAudit\Service\UrlService;

class CheckResultRepository implements CheckResultRepositoryInterface
{
    private $jobCheckFactory;

    private $urlService;

    private $collectionFactory;

    private $entityManager;

    private $collectionProcessor;

    private $searchResultsFactory;

    /** @var AbstractCheck[] */
    private $pool;

    public function __construct(
        CheckResultFactory                        $jobCheckFactory,
        UrlService                                $urlService,
        CollectionFactory                         $collectionFactory,
        EntityManager                             $entityManager,
        CollectionProcessorInterface              $collectionProcessor,
        CheckResultSearchResultsInterfaceFactory  $searchResultsFactory,
        array                                     $pool = []
    ) {
        $this->jobCheckFactory      = $jobCheckFactory;
        $this->urlService           = $urlService;
        $this->collectionFactory    = $collectionFactory;
        $this->entityManager        = $entityManager;
        $this->collectionProcessor  = $collectionProcessor;
        $this->searchResultsFactory = $searchResultsFactory;
        $this->pool                 = $pool;
    }

    public function getCollection(): Collection
    {
        return $this->collectionFactory->create();
    }

    public function create(): CheckResultInterface
    {
        return $this->jobCheckFactory->create();
    }

    public function get(int $id): ?CheckResultInterface
    {
        $jobCheck = $this->create();
        $jobCheck = $this->entityManager->load($jobCheck, $id);

        return $jobCheck->getId() ? $jobCheck : null;
    }

    public function save(CheckResultInterface $jobCheck): CheckResultInterface
    {
        return $this->entityManager->save($jobCheck);
    }

    public function delete(CheckResultInterface $jobCheck): bool
    {
        $this->entityManager->delete($jobCheck);

        return true;
    }

    /**
     * @return AbstractCheck[]
     */
    public function getAllowedChecks(UrlInterface $url): array
    {
        $allowedChecks = [];

        foreach ($this->pool as $check) {
            if ($this->urlService->isExternalUrl($url->getUrl()) && !$check->isAllowedForExternal()) {
                continue;
            }

            if (
                in_array($url->getType(), $check->getAllowedTypes())
                || in_array('all', $check->getAllowedTypes())
            ) {
                $allowedChecks[] = $check;
            }
        }

        return $allowedChecks;
    }

    /**
     * @return AbstractCheck[]
     */
    public function getAllChecks(): array
    {
        return $this->pool;
    }

    public function getCheckInstanceByIdentifier(string $identifier): ?AbstractCheck
    {
        return $this->pool[$identifier] ?? null;
    }

    /**
     * @inheritdoc
     */
    public function getById(int $checkResultId): CheckResultInterface
    {
        $checkResult = $this->create();
        $this->entityManager->load($checkResult, $checkResultId);

        if (!$checkResult->getId()) {
            throw new NoSuchEntityException(
                __('Check result with ID "%1" does not exist.', $checkResultId)
            );
        }

        return $checkResult;
    }

    /**
     * @inheritdoc
     */
    public function getList(SearchCriteriaInterface $searchCriteria): CheckResultSearchResultsInterface
    {
        $collection = $this->collectionFactory->create();
        $this->collectionProcessor->process($searchCriteria, $collection);

        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setSearchCriteria($searchCriteria);
        $searchResults->setItems($collection->getItems());
        $searchResults->setTotalCount($collection->getSize());

        return $searchResults;
    }
}

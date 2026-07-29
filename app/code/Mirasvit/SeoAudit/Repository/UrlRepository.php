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
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\EntityManager\EntityManager;
use Magento\Framework\Exception\NoSuchEntityException;
use Mirasvit\SeoAudit\Api\Data\UrlInterface;
use Mirasvit\SeoAudit\Api\Data\UrlSearchResultsInterface;
use Mirasvit\SeoAudit\Api\Data\UrlSearchResultsInterfaceFactory;
use Mirasvit\SeoAudit\Api\UrlRepositoryInterface;
use Mirasvit\SeoAudit\Model\ResourceModel\Url\Collection;
use Mirasvit\SeoAudit\Model\ResourceModel\Url\CollectionFactory;
use Mirasvit\SeoAudit\Model\UrlFactory;

class UrlRepository implements UrlRepositoryInterface
{
    private $urlFactory;

    private $collectionFactory;

    private $entityManager;

    private $collectionProcessor;

    private $searchResultsFactory;

    private $resourceConnection;

    public function __construct(
        UrlFactory                       $urlFactory,
        CollectionFactory                $collectionFactory,
        EntityManager                    $entityManager,
        CollectionProcessorInterface     $collectionProcessor,
        UrlSearchResultsInterfaceFactory $searchResultsFactory,
        ResourceConnection               $resourceConnection
    ) {
        $this->urlFactory           = $urlFactory;
        $this->collectionFactory    = $collectionFactory;
        $this->entityManager        = $entityManager;
        $this->collectionProcessor  = $collectionProcessor;
        $this->searchResultsFactory = $searchResultsFactory;
        $this->resourceConnection   = $resourceConnection;
    }

    public function getCollection(): Collection
    {
        return $this->collectionFactory->create();
    }

    public function getUnprocessedUrlsCollection(): Collection
    {
        return $this->getCollection()
            ->addFieldToFilter(UrlInterface::STATUS, ['eq' => UrlInterface::STATUS_PENDING]);
    }

    public function getUrlsCollectionForCheck(int $jobId): Collection
    {
        return $this->getCollection()
            ->addFieldToFilter(UrlInterface::STATUS, ['eq' => UrlInterface::STATUS_CRAWLED])
            ->addFieldToFilter(UrlInterface::JOB_ID, ['eq' => $jobId]);
    }

    public function create(): UrlInterface
    {
        return $this->urlFactory->create();
    }

    public function get(int $id): ?UrlInterface
    {
        $url = $this->create();
        $url = $this->entityManager->load($url, $id);

        return $url->getId() ? $url : null;
    }

    public function getByUrl(string $url): ?UrlInterface
    {
        /** @var UrlInterface $crawledUrl */
        $crawledUrl = $this->getCollection()
            ->addFieldToFilter(UrlInterface::URL_HASH, sha1($url))
            ->getFirstItem();

        return $crawledUrl && $crawledUrl->getId() ? $crawledUrl : null;
    }

    public function save(UrlInterface $url): UrlInterface
    {
        $url = $this->entityManager->save($url);

        $this->syncParentRelations($url);

        return $url;
    }

    public function delete(UrlInterface $url): bool
    {
        $urlId = $url->getId();

        // detach this url from any children that still reference it as a parent
        $related = $this->getCollection()
            ->addParentIdFilter($urlId);

        /** @var UrlInterface $r */
        foreach ($related as $r) {
            $updatedParentIds = array_diff($r->getParentIds(), [$urlId]);

            $r->setParentIds($updatedParentIds);

            $this->save($r);
        }

        $this->entityManager->delete($url);

        // drop the deleted url's own relation rows (both as child and as parent)
        $connection    = $this->resourceConnection->getConnection();
        $relationTable = $this->resourceConnection->getTableName(UrlInterface::PARENT_TABLE_NAME);

        $connection->delete($relationTable, [UrlInterface::PARENT_REL_URL_ID . ' = ?' => $urlId]);
        $connection->delete($relationTable, [UrlInterface::PARENT_REL_PARENT_ID . ' = ?' => $urlId]);

        return true;
    }

    private function syncParentRelations(UrlInterface $url): void
    {
        $connection    = $this->resourceConnection->getConnection();
        $relationTable = $this->resourceConnection->getTableName(UrlInterface::PARENT_TABLE_NAME);
        $urlId         = $url->getId();

        $connection->delete($relationTable, [UrlInterface::PARENT_REL_URL_ID . ' = ?' => $urlId]);

        $rows = [];
        foreach (array_unique($url->getParentIds()) as $parentId) {
            // 0 is the "no real parent" sentinel and is never a lookup target — skip it
            if ($parentId > 0) {
                $rows[] = [
                    UrlInterface::PARENT_REL_URL_ID    => $urlId,
                    UrlInterface::PARENT_REL_PARENT_ID => $parentId,
                ];
            }
        }

        if ($rows) {
            $connection->insertMultiple($relationTable, $rows);
        }
    }

    /**
     * @inheritdoc
     */
    public function getById(int $urlId): UrlInterface
    {
        $url = $this->create();
        $this->entityManager->load($url, $urlId);

        if (!$url->getId()) {
            throw new NoSuchEntityException(
                __('URL with ID "%1" does not exist.', $urlId)
            );
        }

        return $url;
    }

    /**
     * @inheritdoc
     */
    public function getList(SearchCriteriaInterface $searchCriteria): UrlSearchResultsInterface
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

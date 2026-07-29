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

namespace Mirasvit\SeoAutolink\Repository;

use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DataObject;
use Magento\Framework\EntityManager\EntityManager;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Mirasvit\SeoAutolink\Api\Data\LinkInterface;
use Mirasvit\SeoAutolink\Api\Data\LinkInterfaceFactory;
use Mirasvit\SeoAutolink\Api\Data\LinkSearchResultsInterface;
use Mirasvit\SeoAutolink\Api\Data\LinkSearchResultsInterfaceFactory;
use Mirasvit\SeoAutolink\Api\LinkRepositoryInterface;
use Mirasvit\SeoAutolink\Model\ResourceModel\Link\CollectionFactory;

class LinkRepository implements LinkRepositoryInterface
{
    private $factory;

    private $collectionFactory;

    private $entityManager;

    private $resource;

    private $collectionProcessor;

    private $searchResultsFactory;

    public function __construct(
        LinkInterfaceFactory               $factory,
        CollectionFactory                  $collectionFactory,
        EntityManager                      $entityManager,
        ResourceConnection                 $resource,
        CollectionProcessorInterface       $collectionProcessor,
        LinkSearchResultsInterfaceFactory  $searchResultsFactory
    ) {
        $this->factory              = $factory;
        $this->collectionFactory    = $collectionFactory;
        $this->entityManager        = $entityManager;
        $this->resource             = $resource;
        $this->collectionProcessor  = $collectionProcessor;
        $this->searchResultsFactory = $searchResultsFactory;
    }

    /**
     * @inheritdoc
     */
    public function getById(int $linkId): LinkInterface
    {
        $model = $this->factory->create();
        $this->entityManager->load($model, $linkId);

        if (!$model->getId()) {
            throw new NoSuchEntityException(
                __('Link with ID "%1" does not exist.', $linkId)
            );
        }

        return $model;
    }

    /**
     * @inheritdoc
     */
    public function getList(SearchCriteriaInterface $searchCriteria): LinkSearchResultsInterface
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
    public function save(LinkInterface $link, ?int $linkId = null): LinkInterface
    {
        $id = $linkId ?? ($link->getId() ? (int)$link->getId() : null);

        if ($id !== null) {
            $model = $this->getById($id);
        } else {
            $model = $this->factory->create();
        }

        $this->mergeIncoming($model, $link);

        if ($model->getKeyword() === null) {
            $model->setKeyword('');
        }
        if ($model->getUrl() === null) {
            $model->setUrl('');
        }

        try {
            $this->entityManager->save($model);
        } catch (\Exception $e) {
            throw new CouldNotSaveException(
                __('Could not save link: %1', $e->getMessage())
            );
        }

        $this->saveStore($model);

        return $model;
    }

    /**
     * @inheritdoc
     */
    public function deleteById(int $linkId): bool
    {
        $model = $this->getById($linkId);

        try {
            $this->entityManager->delete($model);
        } catch (\Exception $e) {
            throw new CouldNotDeleteException(
                __('Could not delete link with ID "%1": %2', $linkId, $e->getMessage())
            );
        }

        return true;
    }

    private function mergeIncoming(LinkInterface $model, LinkInterface $incoming): void
    {
        /** @var DataObject $incoming */

        if ($incoming->hasData(LinkInterface::KEYWORD)) {
            $model->setKeyword($incoming->getKeyword());
        }
        if ($incoming->hasData(LinkInterface::URL)) {
            $model->setUrl($incoming->getUrl());
        }
        if ($incoming->hasData(LinkInterface::URL_TARGET)) {
            $model->setUrlTarget($incoming->getUrlTarget());
        }
        if ($incoming->hasData(LinkInterface::URL_TITLE)) {
            $model->setUrlTitle($incoming->getUrlTitle());
        }
        if ($incoming->hasData(LinkInterface::IS_NOFOLLOW)) {
            $model->setIsNofollow($incoming->getIsNofollow());
        }
        if ($incoming->hasData(LinkInterface::MAX_REPLACEMENTS)) {
            $model->setMaxReplacements($incoming->getMaxReplacements());
        }
        if ($incoming->hasData(LinkInterface::SORT_ORDER)) {
            $model->setSortOrder($incoming->getSortOrder());
        }
        if ($incoming->hasData(LinkInterface::OCCURENCE)) {
            $model->setOccurence($incoming->getOccurence());
        }
        if ($incoming->hasData(LinkInterface::IS_ACTIVE)) {
            $model->setIsActive($incoming->getIsActive());
        }
        if ($incoming->hasData(LinkInterface::ACTIVE_FROM)) {
            $model->setActiveFrom($incoming->getActiveFrom());
        }
        if ($incoming->hasData(LinkInterface::ACTIVE_TO)) {
            $model->setActiveTo($incoming->getActiveTo());
        }
        if ($incoming->hasData('store_ids')) {
            $model->setData('store_ids', $incoming->getData('store_ids'));
        }
        if ($incoming->hasData(LinkInterface::LINK_TYPE)) {
            $model->setLinkType($incoming->getLinkType());
        }
        if ($incoming->hasData(LinkInterface::ENTITY_ID)) {
            $model->setLinkEntityId($incoming->getLinkEntityId());
        }
    }

    private function saveStore(LinkInterface $model): void
    {
        if ($model->getData('store_ids') === null) {
            return;
        }

        $connection = $this->resource->getConnection();
        $table      = $this->resource->getTableName('mst_seoautolink_link_store');
        $condition  = $connection->quoteInto('link_id = ?', $model->getId());

        $connection->delete($table, $condition);

        foreach ((array) $model->getData('store_ids') as $storeId) {
            $connection->insert($table, [
                'link_id'  => $model->getId(),
                'store_id' => $storeId,
            ]);
        }
    }
}

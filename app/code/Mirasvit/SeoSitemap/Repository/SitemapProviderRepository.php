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

namespace Mirasvit\SeoSitemap\Repository;

use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\DataObject;
use Magento\Framework\EntityManager\EntityManager;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Mirasvit\SeoSitemap\Api\Data\ProviderInterface;
use Mirasvit\SeoSitemap\Api\Data\ProviderInterfaceFactory;
use Mirasvit\SeoSitemap\Api\Data\ProviderSearchResultsInterface;
use Mirasvit\SeoSitemap\Api\Data\ProviderSearchResultsInterfaceFactory;
use Mirasvit\SeoSitemap\Api\ProviderRepositoryInterface;
use Mirasvit\SeoSitemap\Model\ResourceModel\Provider\CollectionFactory;

class SitemapProviderRepository implements ProviderRepositoryInterface
{
    private $entityManager;

    private $factory;

    private $collectionFactory;

    private $collectionProcessor;

    private $searchResultsFactory;

    public function __construct(
        EntityManager                          $entityManager,
        ProviderInterfaceFactory               $factory,
        CollectionFactory                      $collectionFactory,
        CollectionProcessorInterface           $collectionProcessor,
        ProviderSearchResultsInterfaceFactory  $searchResultsFactory
    ) {
        $this->entityManager        = $entityManager;
        $this->factory              = $factory;
        $this->collectionFactory    = $collectionFactory;
        $this->collectionProcessor  = $collectionProcessor;
        $this->searchResultsFactory = $searchResultsFactory;
    }

    public function getCollection()
    {
        return $this->collectionFactory->create();
    }

    public function create(): ProviderInterface
    {
        return $this->factory->create();
    }

    public function get(int $id): ?ProviderInterface
    {
        $model = $this->create();
        $this->entityManager->load($model, $id);

        return $model->getId() ? $model : null;
    }

    /**
     * @inheritdoc
     */
    public function getById(int $providerId): ProviderInterface
    {
        $model = $this->create();
        $this->entityManager->load($model, $providerId);

        if (!$model->getId()) {
            throw new NoSuchEntityException(
                __('Provider with ID "%1" does not exist.', $providerId)
            );
        }

        return $model;
    }

    /**
     * @inheritdoc
     */
    public function getList(SearchCriteriaInterface $searchCriteria): ProviderSearchResultsInterface
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
    public function save(ProviderInterface $provider, ?int $providerId = null): ProviderInterface
    {
        $id = $providerId ?? ($provider->getId() ? (int)$provider->getId() : null);

        if ($id !== null) {
            $model = $this->getById($id);
        } else {
            $model = $this->create();
        }

        $this->mergeIncoming($model, $provider);

        try {
            $this->entityManager->save($model);
        } catch (\Exception $e) {
            throw new CouldNotSaveException(
                __('Could not save provider: %1', $e->getMessage())
            );
        }

        return $model;
    }

    /**
     * @inheritdoc
     */
    public function deleteById(int $providerId): bool
    {
        $model = $this->getById($providerId);

        try {
            $this->entityManager->delete($model);
        } catch (\Exception $e) {
            throw new CouldNotDeleteException(
                __('Could not delete provider with ID "%1": %2', $providerId, $e->getMessage())
            );
        }

        return true;
    }

    private function mergeIncoming(ProviderInterface $model, ProviderInterface $incoming): void
    {
        if (!$incoming instanceof DataObject) {
            return;
        }

        if ($incoming->hasData(ProviderInterface::NAME)) {
            $model->setName($incoming->getName());
        }
        if ($incoming->hasData(ProviderInterface::URL)) {
            $model->setUrl($incoming->getUrl());
        }
        if ($incoming->hasData(ProviderInterface::PRIORITY)) {
            $model->setPriority($incoming->getPriority());
        }
        if ($incoming->hasData(ProviderInterface::FREQUENCY)) {
            $model->setFrequency($incoming->getFrequency());
        }
        if ($incoming->hasData(ProviderInterface::IS_ACTIVE)) {
            $model->setIsActive($incoming->getIsActive());
        }
        if ($incoming->hasData(ProviderInterface::STORE_IDS)) {
            $model->setStoreIds($incoming->getStoreIds());
        }
    }
}

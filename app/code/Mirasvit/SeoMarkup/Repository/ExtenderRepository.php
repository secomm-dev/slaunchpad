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

namespace Mirasvit\SeoMarkup\Repository;

use Exception;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Store\Model\Store;
use Mirasvit\SeoMarkup\Api\Data\ExtenderInterface;
use Mirasvit\SeoMarkup\Api\Data\ExtenderInterfaceFactory as Factory;
use Mirasvit\SeoMarkup\Api\Data\ExtenderSearchResultsInterface;
use Mirasvit\SeoMarkup\Api\Data\ExtenderSearchResultsInterfaceFactory;
use Mirasvit\SeoMarkup\Api\ExtenderRepositoryInterface;
use Mirasvit\SeoMarkup\Model\ResourceModel\Extender;
use Mirasvit\SeoMarkup\Model\ResourceModel\Extender\Collection;
use Mirasvit\SeoMarkup\Model\ResourceModel\Extender\CollectionFactory;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class ExtenderRepository implements ExtenderRepositoryInterface
{
    private $resource;

    private $factory;

    private $collectionFactory;

    private $collectionProcessor;

    private $searchResultsFactory;

    private $listForProductCache = [];

    private $serializer;

    public function __construct(
        Extender                              $resource,
        Factory                               $factory,
        CollectionFactory                     $collectionFactory,
        CollectionProcessorInterface          $collectionProcessor,
        ExtenderSearchResultsInterfaceFactory $searchResultsFactory,
        Json                                  $serializer
    ) {
        $this->resource             = $resource;
        $this->factory              = $factory;
        $this->collectionFactory    = $collectionFactory;
        $this->collectionProcessor  = $collectionProcessor;
        $this->searchResultsFactory = $searchResultsFactory;
        $this->serializer           = $serializer;
    }

    public function getCollection(): Collection
    {
        return $this->collectionFactory->create();
    }

    /**
     * @return ExtenderInterface[]
     */
    public function getListForProduct(ProductInterface $product, string $entityTypeId, int $storeId): array
    {
        $cacheKey = $entityTypeId . '|' . $storeId;

        if (!isset($this->listForProductCache[$cacheKey])) {
            $extenders = $this->getCollection()
                ->addFieldToFilter(ExtenderInterface::IS_ACTIVE, ['eq' => 1])
                ->addFieldToFilter(ExtenderInterface::ENTITY_TYPE_ID, ['eq' => $entityTypeId])
                ->addFieldToFilter(
                    ExtenderInterface::STORE_IDS,
                    [['finset' => Store::DEFAULT_STORE_ID], ['finset' => $storeId]]
                )
                ->getItems();

            $decoded = [];
            foreach ($extenders as $extender) {
                try {
                    $snippetArray = $this->serializer->unserialize($extender->getSnippet());
                } catch (\Exception $e) {
                    $snippetArray = null;
                }
                if ($snippetArray) {
                    $extender->setSnippetArray($snippetArray);
                    $decoded[] = $extender;
                }
            }
            $this->listForProductCache[$cacheKey] = $decoded;
        }

        $snippetExtenders = [];
        foreach ($this->listForProductCache[$cacheKey] as $extender) {
            if ($extender->getConditions()->validate($product)) {
                $snippetExtenders[] = $extender;
            }
        }

        return $snippetExtenders;
    }

    /**
     * @throws NoSuchEntityException
     */
    public function get(int $extenderId): ExtenderInterface
    {
        $extender = $this->factory->create();
        $this->resource->load($extender, $extenderId);
        if (!$extender->getId()) {
            throw new NoSuchEntityException(__('The rich snippet extender with ID "%1" doesn\'t exist.', $extenderId));
        }

        return $extender;
    }

    /**
     * @inheritdoc
     */
    public function getById(int $extenderId): ExtenderInterface
    {
        $extender = $this->factory->create();
        $this->resource->load($extender, $extenderId);

        if (!$extender->getId()) {
            throw new NoSuchEntityException(
                __('Extender with ID "%1" does not exist.', $extenderId)
            );
        }

        return $extender;
    }

    /**
     * @inheritdoc
     */
    public function getList(SearchCriteriaInterface $searchCriteria): ExtenderSearchResultsInterface
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
     * @throws CouldNotSaveException
     */
    public function save(ExtenderInterface $extender, ?int $extenderId = null): ExtenderInterface
    {
        $id = $extenderId ?? ($extender->getId() ? (int)$extender->getId() : null);

        if ($id !== null) {
            $model = $this->getById($id);
        } else {
            $model = $this->factory->create();
        }

        $this->mergeIncoming($model, $extender);

        try {
            $this->resource->save($model);
        } catch (Exception $exception) {
            throw new CouldNotSaveException(__('Could not save extender: %1', $exception->getMessage()));
        }

        return $model;
    }

    /**
     * @throws CouldNotDeleteException
     */
    public function delete(ExtenderInterface $extender): bool
    {
        try {
            $this->resource->delete($extender);
        } catch (Exception $exception) {
            throw new CouldNotDeleteException(__($exception->getMessage()));
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function deleteById(int $extenderId): bool
    {
        $model = $this->getById($extenderId);

        try {
            $this->resource->delete($model);
        } catch (Exception $exception) {
            throw new CouldNotDeleteException(
                __('Could not delete extender with ID "%1": %2', $extenderId, $exception->getMessage())
            );
        }

        return true;
    }

    private function mergeIncoming(ExtenderInterface $model, ExtenderInterface $incoming): void
    {
        /** @var DataObject $incoming */
        if ($incoming->hasData(ExtenderInterface::NAME)) {
            $model->setName($incoming->getName());
        }
        if ($incoming->hasData(ExtenderInterface::IS_ACTIVE)) {
            $model->setIsActive($incoming->getIsActive());
        }
        if ($incoming->hasData(ExtenderInterface::ENTITY_TYPE_ID)) {
            $model->setEntityTypeId($incoming->getEntityTypeId());
        }
        if ($incoming->hasData(ExtenderInterface::STORE_IDS)) {
            $model->setStoreIds($incoming->getStoreIds());
        }
        if ($incoming->hasData(ExtenderInterface::SNIPPET)) {
            $model->setSnippet($incoming->getSnippet());
        }
        if ($incoming->hasData(ExtenderInterface::OVERRIDE)) {
            $model->setOverride($incoming->getOverride());
        }
        if ($incoming->hasData(ExtenderInterface::CONDITIONS_SERIALIZED)) {
            $model->setConditionsSerialized($incoming->getConditionsSerialized() ?? '');
        }
    }
}

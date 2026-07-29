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

namespace Mirasvit\Seo\Repository;

use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\DataObject;
use Magento\Framework\EntityManager\EntityManager;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\Serializer\Json;
use Mirasvit\Seo\Api\CanonicalRewriteRepositoryInterface;
use Mirasvit\Seo\Api\Data\CanonicalRewriteInterface;
use Mirasvit\Seo\Api\Data\CanonicalRewriteInterfaceFactory;
use Mirasvit\Seo\Api\Data\CanonicalRewriteSearchResultsInterface;
use Mirasvit\Seo\Api\Data\CanonicalRewriteSearchResultsInterfaceFactory;
use Mirasvit\Seo\Api\Data\CanonicalRewriteStoreInterface;
use Mirasvit\Seo\Api\Repository\CanonicalRewriteRepositoryInterface as LegacyRepositoryInterface;
use Mirasvit\Seo\Model\ResourceModel\CanonicalRewrite\CollectionFactory;

class CanonicalRewriteRepository implements CanonicalRewriteRepositoryInterface, LegacyRepositoryInterface
{
    private $factory;

    private $collectionFactory;

    private $entityManager;

    private $resource;

    private $collectionProcessor;

    private $searchResultsFactory;

    private $serializer;

    public function __construct(
        CanonicalRewriteInterfaceFactory               $factory,
        CollectionFactory                              $collectionFactory,
        EntityManager                                  $entityManager,
        \Magento\Framework\App\ResourceConnection      $resource,
        CollectionProcessorInterface                   $collectionProcessor,
        CanonicalRewriteSearchResultsInterfaceFactory  $searchResultsFactory,
        Json                                           $serializer
    ) {
        $this->factory              = $factory;
        $this->collectionFactory    = $collectionFactory;
        $this->entityManager        = $entityManager;
        $this->resource             = $resource;
        $this->collectionProcessor  = $collectionProcessor;
        $this->searchResultsFactory = $searchResultsFactory;
        $this->serializer           = $serializer;
    }

    public function create()
    {
        return $this->factory->create();
    }

    public function getCollection()
    {
        return $this->collectionFactory->create();
    }

    public function get($id)
    {
        $model = $this->create();
        $this->entityManager->load($model, $id);

        return $model->getId() ? $model : false;
    }

    public function delete(CanonicalRewriteInterface $model)
    {
        return $this->entityManager->delete($model);
    }

    /**
     * @inheritdoc
     */
    public function getById(int $canonicalRewriteId): CanonicalRewriteInterface
    {
        $model = $this->create();
        $this->entityManager->load($model, $canonicalRewriteId);

        if (!$model->getId()) {
            throw new NoSuchEntityException(
                __('Canonical rewrite with ID "%1" does not exist.', $canonicalRewriteId)
            );
        }

        return $model;
    }

    /**
     * @inheritdoc
     */
    public function getList(SearchCriteriaInterface $searchCriteria): CanonicalRewriteSearchResultsInterface
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
    public function save(CanonicalRewriteInterface $canonicalRewrite, ?int $canonicalRewriteId = null): CanonicalRewriteInterface
    {
        $id = $canonicalRewriteId ?? ($canonicalRewrite->getId() ? (int)$canonicalRewrite->getId() : null);

        if ($id !== null) {
            $model = $this->getById($id);
        } else {
            $model = $this->create();
        }

        $this->prepareRuleData($canonicalRewrite);
        $this->mergeIncoming($model, $canonicalRewrite);

        if ($model->getConditionsSerialized() === null) {
            $model->setConditionsSerialized('[]');
        }
        if ($model->getActionsSerialized() === null) {
            $model->setActionsSerialized('[]');
        }

        try {
            $this->entityManager->save($model);
        } catch (\Exception $e) {
            throw new CouldNotSaveException(
                __('Could not save canonical rewrite: %1', $e->getMessage())
            );
        }

        $this->saveStore($model);

        return $model;
    }

    /**
     * @inheritdoc
     */
    public function deleteById(int $canonicalRewriteId): bool
    {
        $model = $this->getById($canonicalRewriteId);

        try {
            $this->entityManager->delete($model);
        } catch (\Exception $e) {
            throw new CouldNotDeleteException(
                __('Could not delete canonical rewrite with ID "%1": %2', $canonicalRewriteId, $e->getMessage())
            );
        }

        return true;
    }

    protected function prepareRuleData($model)
    {
        if ($model->getConditions()
            && ($ruleData = $model->getData('rule'))) {
            $model->loadPost($ruleData);
            $conditions = $this->serializer->serialize($model->getConditions()->asArray());
            $model->setConditionsSerialized($conditions);
        }

        if ($model->getActions()) {
            $actions = $this->serializer->serialize($model->getActions()->asArray());
            $model->setActionsSerialized($actions);
        }
    }

    protected function saveStore($model)
    {
        if ($model->getData('store_ids')) {
            $connection = $this->resource->getConnection();
            $condition = $connection->quoteInto(
                CanonicalRewriteStoreInterface::CANONICAL_REWRITE_ID . ' = ?',
                $model->getId()
            );
            $connection->delete($this->resource->getTableName(CanonicalRewriteStoreInterface::TABLE_NAME), $condition);
            foreach ((array)$model->getData('store_ids') as $store) {
                $storeArray = [
                    CanonicalRewriteStoreInterface::CANONICAL_REWRITE_ID => $model->getId(),
                    CanonicalRewriteStoreInterface::STORE_ID => $store,
                ];
                $connection->insert(
                    $this->resource->getTableName(CanonicalRewriteStoreInterface::TABLE_NAME),
                    $storeArray
                );
            }
        }
    }

    private function mergeIncoming(CanonicalRewriteInterface $model, CanonicalRewriteInterface $incoming): void
    {
        /** @var DataObject $incoming */

        if ($incoming->hasData(CanonicalRewriteInterface::IS_ACTIVE)) {
            $model->setIsActive($incoming->getIsActive());
        }
        if ($incoming->hasData(CanonicalRewriteInterface::CANONICAL)) {
            $model->setCanonical($incoming->getCanonical());
        }
        if ($incoming->hasData(CanonicalRewriteInterface::REG_EXPRESSION)) {
            $model->setRegExpression($incoming->getRegExpression());
        }
        if ($incoming->hasData(CanonicalRewriteInterface::CONDITIONS_SERIALIZED)) {
            $model->setConditionsSerialized($incoming->getConditionsSerialized());
        }
        if ($incoming->hasData(CanonicalRewriteInterface::ACTIONS_SERIALIZED)) {
            $model->setActionsSerialized($incoming->getActionsSerialized());
        }
        if ($incoming->hasData(CanonicalRewriteInterface::SORT_ORDER)) {
            $model->setSortOrder($incoming->getSortOrder());
        }
        if ($incoming->hasData(CanonicalRewriteInterface::COMMENTS)) {
            $model->setComments($incoming->getComments());
        }
        if ($incoming->hasData(CanonicalRewriteInterface::STORE_IDS)) {
            $model->setStoreIds($incoming->getStoreIds());
        }
    }
}

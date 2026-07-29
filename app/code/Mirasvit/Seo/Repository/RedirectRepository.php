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
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DataObject;
use Magento\Framework\EntityManager\EntityManager;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Mirasvit\Seo\Api\Data\RedirectInterface;
use Mirasvit\Seo\Api\Data\RedirectInterfaceFactory;
use Mirasvit\Seo\Api\Data\RedirectSearchResultsInterface;
use Mirasvit\Seo\Api\Data\RedirectSearchResultsInterfaceFactory;
use Mirasvit\Seo\Api\RedirectRepositoryInterface;
use Mirasvit\Seo\Model\ResourceModel\Redirect\CollectionFactory;

class RedirectRepository implements RedirectRepositoryInterface
{
    private $factory;

    private $collectionFactory;

    private $entityManager;

    private $resource;

    private $collectionProcessor;

    private $searchResultsFactory;

    public function __construct(
        RedirectInterfaceFactory               $factory,
        CollectionFactory                      $collectionFactory,
        EntityManager                          $entityManager,
        ResourceConnection                     $resource,
        CollectionProcessorInterface           $collectionProcessor,
        RedirectSearchResultsInterfaceFactory  $searchResultsFactory
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
    public function getById(int $redirectId): RedirectInterface
    {
        $model = $this->factory->create();
        $this->entityManager->load($model, $redirectId);

        if (!$model->getId()) {
            throw new NoSuchEntityException(
                __('Redirect with ID "%1" does not exist.', $redirectId)
            );
        }

        return $model;
    }

    /**
     * @inheritdoc
     */
    public function getList(SearchCriteriaInterface $searchCriteria): RedirectSearchResultsInterface
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
    public function save(RedirectInterface $redirect, ?int $redirectId = null): RedirectInterface
    {
        $id = $redirectId ?? ($redirect->getId() ? (int)$redirect->getId() : null);

        if ($id !== null) {
            $model = $this->getById($id);
        } else {
            $model = $this->factory->create();
        }

        $this->mergeIncoming($model, $redirect);

        if ($model->getUrlFrom() === null) {
            $model->setUrlFrom('');
        }
        if ($model->getUrlTo() === null) {
            $model->setUrlTo('');
        }

        try {
            $this->entityManager->save($model);
        } catch (\Exception $e) {
            throw new CouldNotSaveException(
                __('Could not save redirect: %1', $e->getMessage())
            );
        }

        $this->saveStore($model);

        return $model;
    }

    /**
     * @inheritdoc
     */
    public function deleteById(int $redirectId): bool
    {
        $model = $this->getById($redirectId);

        try {
            $this->entityManager->delete($model);
        } catch (\Exception $e) {
            throw new CouldNotDeleteException(
                __('Could not delete redirect with ID "%1": %2', $redirectId, $e->getMessage())
            );
        }

        return true;
    }

    private function mergeIncoming(RedirectInterface $model, RedirectInterface $incoming): void
    {
        /** @var DataObject $incoming */

        if ($incoming->hasData(RedirectInterface::URL_FROM)) {
            $model->setUrlFrom($incoming->getUrlFrom());
        }
        if ($incoming->hasData(RedirectInterface::URL_TO)) {
            $model->setUrlTo($incoming->getUrlTo());
        }
        if ($incoming->hasData(RedirectInterface::IS_REDIRECT_ONLY_ERROR_PAGE)) {
            $model->setIsRedirectOnlyErrorPage($incoming->getIsRedirectOnlyErrorPage());
        }
        if ($incoming->hasData(RedirectInterface::COMMENTS)) {
            $model->setComments($incoming->getComments());
        }
        if ($incoming->hasData(RedirectInterface::IS_ACTIVE)) {
            $model->setIsActive($incoming->getIsActive());
        }
        if ($incoming->hasData(RedirectInterface::REDIRECT_TYPE)) {
            $model->setRedirectType($incoming->getRedirectType());
        }
        if ($incoming->hasData('store_ids')) {
            $model->setData('store_ids', $incoming->getData('store_ids'));
        }
    }

    private function saveStore(RedirectInterface $model): void
    {
        if ($model->getData('store_ids') === null) {
            return;
        }

        $connection = $this->resource->getConnection();
        $table      = $this->resource->getTableName('mst_seo_redirect_store');
        $condition  = $connection->quoteInto('redirect_id = ?', $model->getId());

        $connection->delete($table, $condition);

        foreach ((array) $model->getData('store_ids') as $storeId) {
            $connection->insert($table, [
                'redirect_id' => $model->getId(),
                'store_id'    => $storeId,
            ]);
        }
    }
}

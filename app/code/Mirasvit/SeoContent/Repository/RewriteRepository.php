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

namespace Mirasvit\SeoContent\Repository;

use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\DataObject;
use Magento\Framework\EntityManager\EntityManager;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Mirasvit\SeoContent\Api\Data\RewriteInterface;
use Mirasvit\SeoContent\Api\Data\RewriteSearchResultsInterface;
use Mirasvit\SeoContent\Api\Data\RewriteSearchResultsInterfaceFactory;
use Mirasvit\SeoContent\Api\Repository\RewriteRepositoryInterface;
use Mirasvit\SeoContent\Api\RewriteRepositoryInterface as WebApiRewriteRepositoryInterface;
use Mirasvit\SeoContent\Model\RewriteFactory;
use Mirasvit\SeoContent\Model\ResourceModel\Rewrite\CollectionFactory;

class RewriteRepository implements RewriteRepositoryInterface, WebApiRewriteRepositoryInterface
{
    private $factory;

    private $collectionFactory;

    private $entityManager;

    private $collectionProcessor;

    private $searchResultsFactory;

    public function __construct(
        RewriteFactory                        $factory,
        CollectionFactory                     $collectionFactory,
        EntityManager                         $entityManager,
        CollectionProcessorInterface          $collectionProcessor,
        RewriteSearchResultsInterfaceFactory  $searchResultsFactory
    ) {
        $this->factory              = $factory;
        $this->collectionFactory    = $collectionFactory;
        $this->entityManager        = $entityManager;
        $this->collectionProcessor  = $collectionProcessor;
        $this->searchResultsFactory = $searchResultsFactory;
    }

    public function getCollection()
    {
        return $this->collectionFactory->create();
    }

    public function create()
    {
        return $this->factory->create();
    }

    public function get($id)
    {
        $rewrite = $this->create();
        $rewrite = $this->entityManager->load($rewrite, $id);

        if (!$rewrite->getId()) {
            return false;
        }

        return $rewrite;
    }

    public function delete(RewriteInterface $rewrite)
    {
        $this->entityManager->delete($rewrite);

        return true;
    }

    /**
     * @inheritdoc
     */
    public function getById(int $rewriteId): RewriteInterface
    {
        $model = $this->create();
        $this->entityManager->load($model, $rewriteId);

        if (!$model->getId()) {
            throw new NoSuchEntityException(
                __('Rewrite with ID "%1" does not exist.', $rewriteId)
            );
        }

        return $model;
    }

    /**
     * @inheritdoc
     */
    public function getList(SearchCriteriaInterface $searchCriteria): RewriteSearchResultsInterface
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
    public function save(RewriteInterface $rewrite, ?int $rewriteId = null): RewriteInterface
    {
        $id = $rewriteId ?? ($rewrite->getId() ? (int)$rewrite->getId() : null);

        if ($id !== null) {
            $model = $this->getById($id);
        } else {
            $model = $this->create();
        }

        $this->mergeIncoming($model, $rewrite);

        try {
            $this->entityManager->save($model);
        } catch (\Exception $e) {
            throw new CouldNotSaveException(
                __('Could not save rewrite: %1', $e->getMessage())
            );
        }

        return $model;
    }

    /**
     * @inheritdoc
     */
    public function deleteById(int $rewriteId): bool
    {
        $model = $this->getById($rewriteId);

        try {
            $this->entityManager->delete($model);
        } catch (\Exception $e) {
            throw new CouldNotDeleteException(
                __('Could not delete rewrite with ID "%1": %2', $rewriteId, $e->getMessage())
            );
        }

        return true;
    }

    private function mergeIncoming(RewriteInterface $model, RewriteInterface $incoming): void
    {
        /** @var DataObject $incoming */
        if ($incoming->hasData(RewriteInterface::URL)) {
            $model->setUrl($incoming->getUrl() ?? '');
        }
        if ($incoming->hasData(RewriteInterface::IS_ACTIVE)) {
            $model->setIsActive($incoming->getIsActive() ?? false);
        }
        if ($incoming->hasData(RewriteInterface::SORT_ORDER)) {
            $model->setSortOrder((string)($incoming->getSortOrder() ?? ''));
        }
        if ($incoming->hasData(RewriteInterface::STORE_IDS)) {
            $model->setStoreIds($incoming->getStoreIds());
        }
        if ($incoming->hasData(RewriteInterface::ADD_TO_SITEMAP)) {
            $model->setAddToSitemap($incoming->getAddToSitemap());
        }
        if ($incoming->hasData(RewriteInterface::USE_IN_BREADCRUMBS)) {
            $model->setUseInBreadcrumbs($incoming->getUseInBreadcrumbs());
        }
        if ($incoming->hasData(RewriteInterface::TITLE)) {
            $model->setTitle($incoming->getTitle());
        }
        if ($incoming->hasData(RewriteInterface::META_TITLE)) {
            $model->setMetaTitle($incoming->getMetaTitle());
        }
        if ($incoming->hasData(RewriteInterface::META_KEYWORDS)) {
            $model->setMetaKeywords($incoming->getMetaKeywords());
        }
        if ($incoming->hasData(RewriteInterface::META_DESCRIPTION)) {
            $model->setMetaDescription($incoming->getMetaDescription());
        }
        if ($incoming->hasData(RewriteInterface::META_ROBOTS)) {
            $model->setMetaRobots($incoming->getMetaRobots());
        }
        if ($incoming->hasData(RewriteInterface::DESCRIPTION)) {
            $model->setDescription($incoming->getDescription());
        }
        if ($incoming->hasData(RewriteInterface::DESCRIPTION_POSITION)) {
            $model->setDescriptionPosition($incoming->getDescriptionPosition());
        }
        if ($incoming->hasData(RewriteInterface::DESCRIPTION_TEMPLATE)) {
            $model->setDescriptionTemplate($incoming->getDescriptionTemplate());
        }
        if ($incoming->hasData(RewriteInterface::SHORT_DESCRIPTION)) {
            $model->setShortDescription($incoming->getShortDescription());
        }
        if ($incoming->hasData(RewriteInterface::FULL_DESCRIPTION)) {
            $model->setFullDescription($incoming->getFullDescription());
        }
        if ($incoming->hasData(RewriteInterface::CATEGORY_DESCRIPTION)) {
            $model->setCategoryDescription($incoming->getCategoryDescription());
        }
        if ($incoming->hasData(RewriteInterface::CATEGORY_IMAGE)) {
            $model->setCategoryImage($incoming->getCategoryImage());
        }
        if ($incoming->hasData(RewriteInterface::BRAND_DESCRIPTION)) {
            $model->setBrandDescription($incoming->getBrandDescription());
        }
    }
}

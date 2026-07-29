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
use Mirasvit\SeoContent\Api\Data\TemplateInterface;
use Mirasvit\SeoContent\Api\Data\TemplateSearchResultsInterface;
use Mirasvit\SeoContent\Api\Data\TemplateSearchResultsInterfaceFactory;
use Mirasvit\SeoContent\Api\Repository\TemplateRepositoryInterface;
use Mirasvit\SeoContent\Api\TemplateRepositoryInterface as WebApiTemplateRepositoryInterface;
use Mirasvit\SeoContent\Model\TemplateFactory;
use Mirasvit\SeoContent\Model\ResourceModel\Template\CollectionFactory;

class TemplateRepository implements TemplateRepositoryInterface, WebApiTemplateRepositoryInterface
{
    private $factory;

    private $collectionFactory;

    private $entityManager;

    private $collectionProcessor;

    private $searchResultsFactory;

    public function __construct(
        TemplateFactory                        $factory,
        CollectionFactory                      $collectionFactory,
        EntityManager                          $entityManager,
        CollectionProcessorInterface           $collectionProcessor,
        TemplateSearchResultsInterfaceFactory  $searchResultsFactory
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
        $template = $this->create();
        $template = $this->entityManager->load($template, $id);

        if (!$template->getId()) {
            return false;
        }

        return $template;
    }

    public function delete(TemplateInterface $template)
    {
        $this->entityManager->delete($template);

        return true;
    }

    /**
     * @inheritdoc
     */
    public function getById(int $templateId): TemplateInterface
    {
        $model = $this->create();
        $this->entityManager->load($model, $templateId);

        if (!$model->getId()) {
            throw new NoSuchEntityException(
                __('Template with ID "%1" does not exist.', $templateId)
            );
        }

        return $model;
    }

    /**
     * @inheritdoc
     */
    public function getList(SearchCriteriaInterface $searchCriteria): TemplateSearchResultsInterface
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
    public function save(TemplateInterface $template, ?int $templateId = null): TemplateInterface
    {
        $id = $templateId ?? ($template->getId() ? (int)$template->getId() : null);

        if ($id !== null) {
            $model = $this->getById($id);
        } else {
            $model = $this->create();
        }

        $this->mergeIncoming($model, $template);

        if ($model->getConditionsSerialized() === null) {
            $model->setConditionsSerialized('[]');
        }

        try {
            $this->entityManager->save($model);
        } catch (\Exception $e) {
            throw new CouldNotSaveException(
                __('Could not save template: %1', $e->getMessage())
            );
        }

        return $model;
    }

    /**
     * @inheritdoc
     */
    public function deleteById(int $templateId): bool
    {
        $model = $this->getById($templateId);

        try {
            $this->entityManager->delete($model);
        } catch (\Exception $e) {
            throw new CouldNotDeleteException(
                __('Could not delete template with ID "%1": %2', $templateId, $e->getMessage())
            );
        }

        return true;
    }

    private function mergeIncoming(TemplateInterface $model, TemplateInterface $incoming): void
    {
        /** @var DataObject $incoming */
        if ($incoming->hasData(TemplateInterface::RULE_TYPE)) {
            $model->setRuleType((int)($incoming->getRuleType() ?? 0));
        }
        if ($incoming->hasData(TemplateInterface::NAME)) {
            $model->setName($incoming->getName());
        }
        if ($incoming->hasData(TemplateInterface::IS_ACTIVE)) {
            $model->setIsActive($incoming->getIsActive());
        }
        if ($incoming->hasData(TemplateInterface::SORT_ORDER)) {
            $model->setSortOrder($incoming->getSortOrder());
        }
        if ($incoming->hasData(TemplateInterface::STOP_RULE_PROCESSING)) {
            $model->setStopRulesProcessing($incoming->getStopRulesProcessing());
        }
        if ($incoming->hasData(TemplateInterface::APPLY_FOR_CHILD_CATEGORIES)) {
            $model->setApplyForChildCategories($incoming->getApplyForChildCategories());
        }
        if ($incoming->hasData(TemplateInterface::CONDITIONS_SERIALIZED)) {
            $model->setConditionsSerialized($incoming->getConditionsSerialized() ?? '');
        }
        if ($incoming->hasData(TemplateInterface::STORE_IDS)) {
            $model->setStoreIds($incoming->getStoreIds());
        }
        if ($incoming->hasData(TemplateInterface::APPLY_FOR_HOMEPAGE)) {
            $model->setApplyForHomepage($incoming->getApplyForHomepage());
        }
        if ($incoming->hasData(TemplateInterface::APPLY_FOR_ALL_BRANDS_PAGE)) {
            $model->setApplyForAllBrandsPage($incoming->getApplyForAllBrandsPage());
        }
        if ($incoming->hasData(TemplateInterface::APPLY_FOR_LANDING_PAGES)) {
            $model->setApplyForLandingPages($incoming->getApplyForLandingPages());
        }
        if ($incoming->hasData(TemplateInterface::TITLE)) {
            $model->setTitle($incoming->getTitle());
        }
        if ($incoming->hasData(TemplateInterface::META_TITLE)) {
            $model->setMetaTitle($incoming->getMetaTitle());
        }
        if ($incoming->hasData(TemplateInterface::META_KEYWORDS)) {
            $model->setMetaKeywords($incoming->getMetaKeywords());
        }
        if ($incoming->hasData(TemplateInterface::META_DESCRIPTION)) {
            $model->setMetaDescription($incoming->getMetaDescription());
        }
        if ($incoming->hasData(TemplateInterface::META_ROBOTS)) {
            $model->setMetaRobots($incoming->getMetaRobots());
        }
        if ($incoming->hasData(TemplateInterface::DESCRIPTION)) {
            $model->setDescription($incoming->getDescription());
        }
        if ($incoming->hasData(TemplateInterface::DESCRIPTION_POSITION)) {
            $model->setDescriptionPosition($incoming->getDescriptionPosition());
        }
        if ($incoming->hasData(TemplateInterface::DESCRIPTION_TEMPLATE)) {
            $model->setDescriptionTemplate($incoming->getDescriptionTemplate());
        }
        if ($incoming->hasData(TemplateInterface::SHORT_DESCRIPTION)) {
            $model->setShortDescription($incoming->getShortDescription());
        }
        if ($incoming->hasData(TemplateInterface::FULL_DESCRIPTION)) {
            $model->setFullDescription($incoming->getFullDescription());
        }
        if ($incoming->hasData(TemplateInterface::CATEGORY_DESCRIPTION)) {
            $model->setCategoryDescription($incoming->getCategoryDescription());
        }
        if ($incoming->hasData(TemplateInterface::CATEGORY_IMAGE)) {
            $model->setCategoryImage($incoming->getCategoryImage());
        }
        if ($incoming->hasData(TemplateInterface::BRAND_DESCRIPTION)) {
            $model->setBrandDescription($incoming->getBrandDescription());
        }
    }
}

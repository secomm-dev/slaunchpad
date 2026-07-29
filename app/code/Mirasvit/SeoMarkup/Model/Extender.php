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

namespace Mirasvit\SeoMarkup\Model;

use Magento\CatalogRule\Model\Rule\Condition\CombineFactory;
use Magento\Framework\Data\FormFactory;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Rule\Model\AbstractModel;
use Magento\Rule\Model\Action\Collection;
use Magento\Rule\Model\Action\CollectionFactory;
use Magento\Rule\Model\Condition\Combine;
use Magento\Store\Model\Store;
use Mirasvit\SeoMarkup\Api\Data\ExtenderInterface;
use Mirasvit\SeoMarkup\Model\ResourceModel\Extender as Resource;
use Mirasvit\SeoMarkup\Model\ResourceModel\Extender\Collection as ResourceCollection;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Extender extends AbstractModel implements ExtenderInterface
{
    protected $_eventPrefix = 'mx_rich_snippet_extender';

    private   $conditionCombineFactory;

    private   $actionCollectionFactory;

    public function __construct(
        CombineFactory     $conditionCombineFactory,
        CollectionFactory  $actionCollectionFactory,
        Context            $context,
        Registry           $registry,
        FormFactory        $formFactory,
        TimezoneInterface  $localeDate,
        Resource           $resource,
        ResourceCollection $resourceCollection,
        array              $data = []

    ) {
        $this->conditionCombineFactory = $conditionCombineFactory;
        $this->actionCollectionFactory = $actionCollectionFactory;

        parent::__construct($context, $registry, $formFactory, $localeDate, $resource, $resourceCollection, $data);
    }

    protected function _construct()
    {
        $this->_init(Resource::class);
    }

    public function getId(): ?int
    {
        return parent::getId() ? (int)parent::getId() : null;
    }

    public function getExtenderId(): ?int
    {
        $extenderId = $this->getData(self::EXTENDER_ID);

        return $extenderId ? (int)$extenderId : null;
    }

    public function setExtenderId(int $extenderId): ExtenderInterface
    {
        return $this->setData(self::EXTENDER_ID, $extenderId);
    }

    public function getName(): string
    {
        return (string)$this->getData(self::NAME);
    }

    public function setName(string $name): ExtenderInterface
    {
        return $this->setData(self::NAME, $name);
    }

    public function getIsActive(): bool
    {
        return (bool)$this->getData(self::IS_ACTIVE);
    }

    public function setIsActive(bool $isActive): ExtenderInterface
    {
        return $this->setData(self::IS_ACTIVE, $isActive);
    }

    public function getEntityTypeId(): string
    {
        return (string)$this->getData(self::ENTITY_TYPE_ID);
    }

    public function setEntityTypeId(string $entityTypeId): ExtenderInterface
    {
        return $this->setData(self::ENTITY_TYPE_ID, $entityTypeId);
    }

    public function getStoreIds(): array
    {
        $storeIds = $this->getData(self::STORE_IDS);

        if (is_array($storeIds)) {
            return array_map(function ($storeId) {
                return (int)$storeId;
            }, $storeIds);
        }

        return is_string($storeIds) ? array_map('intval', explode(',', $storeIds)) : [];
    }

    public function setStoreIds(array $storeIds): ExtenderInterface
    {
        $storeIds = $storeIds ? implode(',', $storeIds) : Store::DEFAULT_STORE_ID;

        return $this->setData(self::STORE_IDS, $storeIds);
    }

    public function getSnippet(): string
    {
        return (string)$this->getData(self::SNIPPET);
    }

    public function setSnippet(string $snippet): ExtenderInterface
    {
        return $this->setData(self::SNIPPET, $snippet);
    }

    public function getSnippetArray(): array
    {
        return is_array($this->getData(self::SNIPPET_ARRAY)) ? $this->getData(self::SNIPPET_ARRAY) : [];
    }

    public function setSnippetArray(array $snippet): ExtenderInterface
    {
        return $this->setData(self::SNIPPET_ARRAY, $snippet);
    }

    public function getOverride(): bool
    {
        return (bool)$this->getData(self::OVERRIDE);
    }

    public function setOverride(bool $override): ExtenderInterface
    {
        return $this->setData(self::OVERRIDE, $override);
    }

    public function getConditionsSerialized(): ?string
    {
        return $this->getData(self::CONDITIONS_SERIALIZED);
    }

    public function setConditionsSerialized(string $conditions): ExtenderInterface
    {
        return $this->setData(self::CONDITIONS_SERIALIZED, $conditions);
    }

    public function getConditions(): Combine
    {
        if (!($this->_conditions instanceof \Magento\Rule\Model\Condition\Combine)) {
            $this->_resetConditions();
        }

        if ($this->hasConditionsSerialized()) {
            $conditions = $this->getConditionsSerialized();
            if (!empty($conditions)) {
                try {
                    $conditions = $this->serializer->unserialize($conditions);
                } catch (\Exception $e) {
                    $conditions = null;
                }
                if (is_array($conditions) && !empty($conditions)) {
                    $this->_conditions->loadArray($conditions);
                }
            }
            $this->unsConditionsSerialized();
        }

        return $this->_conditions;
    }

    public function getConditionsInstance(): Combine
    {
        return $this->conditionCombineFactory->create();
    }

    public function getActionsInstance(): Collection
    {
        return $this->actionCollectionFactory->create();
    }
}

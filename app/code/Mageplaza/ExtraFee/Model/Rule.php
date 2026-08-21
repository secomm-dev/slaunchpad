<?php
/**
 * Mageplaza
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Mageplaza.com license that is
 * available through the world-wide-web at this URL:
 * https://www.mageplaza.com/LICENSE.txt
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this extension to newer
 * version in the future.
 *
 * @category    Mageplaza
 * @package     Mageplaza_ExtraFee
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\ExtraFee\Model;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\CatalogRule\Model\Rule\Condition\Combine as CatalogCombine;
use Magento\Framework\Api\AttributeValueFactory;
use Magento\Framework\Api\ExtensionAttributesFactory;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Data\FormFactory;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Quote\Model\Quote\Item\AbstractItem;
use Magento\Rule\Model\AbstractModel as RuleAbstractModel;
use Magento\Rule\Model\Condition\Combine;
use Magento\SalesRule\Model\Rule\Condition\Product\Found;
use Mageplaza\ExtraFee\Model\ResourceModel\Rule as RuleResource;
use Mageplaza\ExtraFee\Model\Rule\Action\CombineFactory;
use Mageplaza\ExtraFee\Model\Rule\Condition\CombineFactory as ExtraFeeCombine;

/**
 * Class Rule
 * @package Mageplaza\ExtraFee\Model
 * @method getOptions()
 * @method getFeeTax()
 * @method getRefundable()
 * @method getArea()
 * @method getApplyType()
 * @method getStopFurtherProcessing()
 * @method getStatus()
 * @method getStoreIds()
 * @method getCustomerGroups()
 * @method setType($feeType)
 * @method getFeeType()
 * @method getLabels()
 * @method setOptions($jsonEncode)
 * @method Rule\Action\Combine getActions()
 */
class Rule extends RuleAbstractModel
{
    /**
     * Cache tag
     *
     * @var string
     */
    const CACHE_TAG = 'mageplaza_extrafee_rule';

    /**
     * Cache tag
     *
     * @var string
     */
    protected $_cacheTag = 'mageplaza_extrafee_rule';

    /**
     * Event prefix
     *
     * @var string
     */
    protected $_eventPrefix = 'mageplaza_extrafee_rule';

    /**
     * @var CatalogCombine
     */
    protected $condProdCombine;

    /**
     * @var ExtraFeeCombine
     */
    protected $condCombineFactory;

    /**
     * @var ProductRepositoryInterface
     */
    protected $productRepository;

    /**
     * @var Rule\Action\Combine
     */
    protected $actionCombineFactory;

    /**
     * @param Context $context
     * @param Registry $registry
     * @param FormFactory $formFactory
     * @param TimezoneInterface $localeDate
     * @param CatalogCombine $condProdCombine
     * @param CombineFactory $actionCombineFactory
     * @param ExtraFeeCombine $condCombineFactory
     * @param ProductRepositoryInterface $productRepository
     * @param array $data
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param ExtensionAttributesFactory|null $extensionFactory
     * @param AttributeValueFactory|null $customAttributeFactory
     * @param Json|null $serializer
     */
    public function __construct(
        Context $context,
        Registry $registry,
        FormFactory $formFactory,
        TimezoneInterface $localeDate,
        CatalogCombine $condProdCombine,
        CombineFactory $actionCombineFactory,
        ExtraFeeCombine $condCombineFactory,
        ProductRepositoryInterface $productRepository,
        array $data = [],
        ?AbstractResource $resource = null,
        ?AbstractDb $resourceCollection = null,
        ?ExtensionAttributesFactory $extensionFactory = null,
        ?AttributeValueFactory $customAttributeFactory = null,
        ?Json $serializer = null
    ) {
        $this->condProdCombine      = $condProdCombine;
        $this->actionCombineFactory = $actionCombineFactory;
        $this->condCombineFactory   = $condCombineFactory;
        $this->productRepository    = $productRepository;

        parent::__construct(
            $context,
            $registry,
            $formFactory,
            $localeDate,
            $resource,
            $resourceCollection,
            $data,
            $extensionFactory,
            $customAttributeFactory,
            $serializer
        );
    }

    /**
     * @return void
     * @throws LocalizedException
     */
    protected function _construct()
    {
        $this->_init(RuleResource::class);
    }

    /**
     * @return Rule\Action\Combine
     */
    public function getActionsInstance()
    {
        return $this->actionCombineFactory->create();
    }

    /**
     * @return Combine|Rule\Condition\Combine
     */
    public function getConditionsInstance()
    {
        return $this->condCombineFactory->create();
    }

    /**
     * @param AbstractItem $item
     *
     * @return bool
     */
    public function validateActionsRule($item)
    {
        return $this->getActions()->validate($item);
    }

    /**
     * @param AbstractItem $product
     *
     * @return bool
     */
    public function validateProductActions($product)
    {
        if ($product->getTypeId() === "configurable") {
            $typeInstance = $product->getTypeInstance();
            $usedProducts = $typeInstance->getUsedProducts($product);
            $isContinue   = true;

            foreach ($usedProducts as $child) {
                if ($this->getActions()->validate($child)) {
                    $isContinue = false;
                    break;
                }
            }
            if (!$isContinue) {
                return true;
            }
        }

        return $this->validateActionsRule($product);
    }

    /**
     * Validate rule conditions to determine if rule can run
     *
     * @param DataObject | AbstractModel $object
     * @param bool $isFetch
     *
     * @return bool
     */
    public function validate(DataObject $object, $isFetch = false)
    {
        if ($isFetch && $this->getData('validated_for_fetch') !== null) {
            return $this->getData('validated_for_fetch');
        }

        $validated = $this->getConditions()->validate($object);
        $this->setData('validated_for_fetch', $validated);

        return $validated;
    }

    /**
     * Validate rule conditions to determine if rule can run
     *
     * @param AbstractModel $object
     *
     * @return bool
     */
    public function validateProduct(AbstractModel $object)
    {
        $conditions = $this->getConditions();
        $all        = $conditions->getAggregator() === 'all';
        $true       = (bool) $conditions->getValue();

        foreach ($conditions->getConditions() as $cond) {
            if ($cond instanceof Found) {
                $validated = $cond->validate($object);
            } else {
                // can only validate ProductAttribute
                $validated = true;
            }
            if ($all && $validated !== $true) {
                return false;
            } elseif (!$all && $validated === $true) {
                return true;
            }
        }

        return $all ? true : false;
    }
}

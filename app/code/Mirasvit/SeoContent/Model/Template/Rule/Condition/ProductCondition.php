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



namespace Mirasvit\SeoContent\Model\Template\Rule\Condition;

use Exception;
use Magento\Backend\Model\Url;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Type as ProductType;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute;
use Magento\Catalog\Model\ResourceModel\ProductFactory;
use Magento\CatalogInventory\Model\Stock\ItemFactory;
use Magento\CatalogInventory\Model\StockState;
use Magento\CatalogRule\Model\ResourceModel\RuleFactory;
use Magento\ConfigurableProduct\Model\Product\Type\ConfigurableFactory;
use Magento\Customer\Model\Session;
use Magento\Eav\Model\Config;
use Magento\Eav\Model\ResourceModel\Entity\Attribute\Set\CollectionFactory as EntityAttributeSetCollectionFactory;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Locale\FormatInterface;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Registry;
use Magento\Framework\UrlInterface;
use Magento\Rule\Model\Condition\AbstractCondition;
use Magento\Rule\Model\Condition\Context;
use Magento\Store\Model\StoreManagerInterface;

class ProductCondition extends AbstractCondition
{
    private $stockItemFactory;

    private $entityAttributeSetCollectionFactory;

    private $productFactory;

    private $productTypeConfigurableFactory;

    private $config;

    private $backendUrlManager;

    private $localeFormat;

    private $assetRepo;

    private $registry;

    private $category;

    private $entityAttributeValues = [];

    private $isUsedForRuleProperty = 'is_used_for_promo_rules';

    private $stockState;

    private $productType;

    public function __construct(
        Context                             $context,
        ItemFactory                         $stockItemFactory,
        StockState                          $stockState,
        EntityAttributeSetCollectionFactory $entityAttributeSetCollectionFactory,
        ProductFactory                      $productFactory,
        ConfigurableFactory                 $productTypeConfigurableFactory,
        Config                              $config,
        Url                                 $backendUrlManager,
        FormatInterface                     $localeFormat,
        Registry                            $registry,
        Category                            $category,
        ProductType                         $productType,
        array $data = []
    ) {
        $this->stockItemFactory                    = $stockItemFactory;
        $this->stockState                          = $stockState;
        $this->entityAttributeSetCollectionFactory = $entityAttributeSetCollectionFactory;
        $this->productFactory                      = $productFactory;
        $this->productTypeConfigurableFactory      = $productTypeConfigurableFactory;
        $this->config                              = $config;
        $this->backendUrlManager                   = $backendUrlManager;
        $this->localeFormat                        = $localeFormat;
        $this->assetRepo                           = $context->getAssetRepository();
        $this->registry                            = $registry;
        $this->category                            = $category;
        $this->productType                         = $productType;

        parent::__construct($context, $data);
    }

    /**
     * @return false|Attribute
     */
    public function getAttributeObject()
    {
        try {
            $obj = $this->config
                ->getAttribute('catalog_product', $this->getAttribute());
        } catch (Exception $e) {
            $obj = new DataObject();
            $obj->setEntity($this->productFactory->create())
                ->setFrontendInput('text');
        }

        return $obj;
    }

    /**
     * @return $this
     */
    public function loadAttributeOptions()
    {
        $productAttributes = $this->productFactory->create()
            ->loadAllAttributes()
            ->getAttributesByCode();

        $attributes = [];
        foreach ($productAttributes as $attribute) {
            if (!$attribute->isAllowedForRuleCondition()
                || !$attribute->getDataUsingMethod($this->isUsedForRuleProperty)) {
                continue;
            }
            $attributes[$attribute->getAttributeCode()] = $attribute->getFrontendLabel() . ' (' . $attribute->getAttributeCode() . ')';
        }

        $attributes = $this->addSpecialAttributes($attributes);

        asort($attributes);
        $this->setAttributeOption($attributes);

        return $this;
    }

    /**
     * Retrieve value by option.
     *
     * @param string $option
     *
     * @return string
     */
    public function getValueOption($option = null)
    {
        $this->_prepareValueOptions();

        return $this->getData('value_option' . ($option !== null ? '/' . $option : ''));
    }

    /**
     * Retrieve select option values.
     * @return array
     */
    public function getValueSelectOptions()
    {
        $this->_prepareValueOptions();

        return $this->getData('value_select_options');
    }

    /**
     * Retrieve after element HTML.
     * @return string
     */
    public function getValueAfterElementHtml()
    {
        $html = '';

        switch ($this->getAttribute()) {
            case 'sku':
            case 'category_ids':
                $image = $this->assetRepo->getUrl('images/rule_chooser_trigger.gif');
                break;
        }

        if (!empty($image)) {
            $html
                = '<a href="javascript:void(0)" class="rule-chooser-trigger">
                    <img src="' . $image . '" alt="" class="v-middle rule-chooser-trigger" title="' . __('Open Chooser') . '" />
                    </a>';
        }

        return $html;
    }

    /**
     * Retrieve attribute element.
     * @return AbstractCondition
     */
    public function getAttributeElement()
    {
        $element = parent::getAttributeElement();
        $element->setShowAsText(true);

        return $element;
    }

    /**
     * @param mixed $productCollection
     *
     * @return $this
     */
    public function collectValidatedAttributes($productCollection)
    {
        $attribute = $this->getAttribute();

        if (!in_array($attribute, ['category_ids', 'qty', 'price_diff', 'percent_discount'])) {
            $attrObj = $this->getAttributeObject();
            if ($attrObj !== false && $attrObj->isScopeGlobal()) {
                $attributes             = $this->getRule()->getCollectedAttributes();
                $attributes[$attribute] = true;
                $this->getRule()->setCollectedAttributes($attributes);
                $productCollection->addAttributeToSelect($attribute, 'left');
            } else {
                $this->entityAttributeValues = $productCollection->getAllAttributeValues($attribute);
            }
        } elseif (($attribute == 'price_diff')
            || ($attribute == 'percent_discount')) {
            $productCollection->addAttributeToSelect('price', 'left');
            $productCollection->addAttributeToSelect('special_price', 'left');
            $productCollection->addAttributeToSelect('special_from_date', 'left');
            $productCollection->addAttributeToSelect('special_to_date', 'left');
            $productCollection->addAttributeToSelect('type_id', 'left');
        }

        return $this;
    }

    /**
     * Retrieve input type.
     * @return string
     */
    public function getInputType()
    {
        if ($this->getAttribute() === 'attribute_set_id') {
            return 'select';
        }
        if ($this->getAttribute() === 'type_id') {
            return 'select';
        }
        if (!is_object($this->getAttributeObject())) {
            return 'string';
        }
        switch ($this->getAttributeObject()->getFrontendInput()) {
            case 'select':
                return 'select';

            case 'multiselect':
                return 'multiselect';

            case 'boolean':
                return 'boolean';

            default:
                return 'string';
        }
    }

    /**
     * Retrieve value element type.
     * @return string
     */
    public function getValueElementType()
    {
        if ($this->getAttribute() === 'attribute_set_id') {
            return 'select';
        }
        if ($this->getAttribute() === 'type_id') {
            return 'select';
        }
        if (!is_object($this->getAttributeObject())) {
            return 'text';
        }
        switch ($this->getAttributeObject()->getFrontendInput()) {
            case 'select':
            case 'boolean':
                return 'select';

            case 'multiselect':
                return 'multiselect';

            default:
                return 'text';
        }
    }

    /**
     * Retrieve value element chooser URL.
     * @return string
     */
    public function getValueElementChooserUrl()
    {
        $url = false;
        switch ($this->getAttribute()) {
            case 'sku':
            case 'category_ids':
                $url = 'catalog_rule/promo_widget/chooser'
                    . '/attribute/' . $this->getAttribute();
                if ($this->getJsFormObject()) {
                    $url .= '/form/' . $this->getJsFormObject();
                } else {
                    $url .= '/form/rule_conditions_fieldset';
                }
                break;
        }

        return $url !== false ? $this->backendUrlManager->getUrl($url) : '';
    }

    /**
     * Retrieve Explicit Apply.
     * @return bool
     * @SuppressWarnings(PHPMD.BooleanGetMethodName)
     */
    public function getExplicitApply()
    {
        switch ($this->getAttribute()) {
            case 'sku':
            case 'category_ids':
                return true;
        }

        return false;
    }

    /**
     * Load array.
     *
     * @param array $arr
     *
     * @return $this
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function loadArray($arr)
    {
        $this->setAttribute(isset($arr['attribute']) ? $arr['attribute'] : false);
        $attribute = $this->getAttributeObject();

        if ($attribute && $attribute->getBackendType() == 'decimal') {
            if (isset($arr['value'])) {
                if (!empty($arr['operator'])
                    && in_array($arr['operator'], ['!()', '()'])
                    && false !== strpos($arr['value'], ',')) {
                    $tmp = [];
                    foreach (explode(',', $arr['value']) as $value) {
                        $tmp[] = $this->localeFormat->getNumber($value);
                    }
                    $arr['value'] = implode(',', $tmp);
                } else {
                    $arr['value'] = $this->localeFormat->getNumber($arr['value']);
                }
            } else {
                $arr['value'] = false;
            }
            $arr['is_value_parsed'] = isset($arr['is_value_parsed'])
                ? $this->localeFormat->getNumber($arr['is_value_parsed']) : false;
        }

        return parent::loadArray($arr);
    }

    //breaks "is one of" rule
    /**
     * Commented due to issue with "is one of" rule
     * Retrieve parsed value
     * @return array|string|int|float
     */
    // public function getValueParsed()
    // {
    //     if (!$this->hasValueParsed()) {
    //         $value = $this->getData('value');

    //         $this->setValueParsed($value);
    //     }

    //     return $this->getData('value_parsed');
    // }

    /**
     * Validate product attrbute value for condition.
     *
     * @param AbstractModel $object
     *
     * @return bool
     */
    public function validate(AbstractModel $object)
    {
        if (!($object instanceof Product) && !($object instanceof Category)) {
            return true;
        }

        $attrCode = $this->getAttribute();

        switch ($attrCode) {
            case 'category_ids':
                /** @var Category $object */
                return $this->validateCategory($object);

            case 'attribute_set_id':
                $attrId = $object->getAttributeSetId();

                return $this->validateAttribute($attrId);

            case 'qty':
                /** @var Product $object */
                return $this->validateQty($object);

            default:
                /** @var mixed $object */
                return $this->validateValue($object, $attrCode);
        }
    }

    /**
     * @return $this
     * @throws LocalizedException
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    protected function _prepareValueOptions()
    {
        // Check that both keys exist. Maybe somehow only one was set not in this routine, but externally.
        $selectReady = $this->getData('value_select_options');
        $hashedReady = $this->getData('value_option');
        if ($selectReady && $hashedReady) {
            return $this;
        }

        // Get array of select options. It will be used as source for hashed options
        $selectOptions = null;
        if ($this->getAttribute() === 'attribute_set_id') {
            $entityTypeId  = $this->config
                ->getEntityType('catalog_product')->getId();
            $selectOptions = $this->entityAttributeSetCollectionFactory->create()
                ->setEntityTypeFilter($entityTypeId)
                ->load()
                ->toOptionArray();
        } elseif ($this->getAttribute() === 'type_id') {
            $typeOptions = $this->productType->getOptionArray();
            $selectOptions = [];
            foreach ($typeOptions as $key => $option) {
                $selectOptions[] = ['label' => $option, 'value' => $key];
            }
        } elseif (is_object($this->getAttributeObject())) {
            $attributeObject = $this->getAttributeObject();
            if ($attributeObject->usesSource()) {
                if ($attributeObject->getFrontendInput() == 'multiselect') {
                    $addEmptyOption = false;
                } else {
                    $addEmptyOption = true;
                }
                $source        = $attributeObject->getSource();
                $selectOptions = $source->getAllOptions();
            }
        }

        // Set new values only if we really got them
        if ($selectOptions !== null) {
            // Overwrite only not already existing values
            if (!$selectReady) {
                $this->setData('value_select_options', $selectOptions);
            }
            if (!$hashedReady) {
                $hashedOptions = [];
                foreach ($selectOptions as $o) {
                    if (is_array($o['value'])) {
                        continue; // We cannot use array as index
                    }
                    $hashedOptions[$o['value']] = $o['label'];
                }
                $this->setData('value_option', $hashedOptions);
            }
        }

        return $this;
    }

    /**
     * @param Category $object
     *
     * @return bool
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    protected function validateCategory($object)
    {
        if ($object instanceof Category) {
            $categoryIds = [$object->getId()];
            $categoryIds = $this->prepareCategoryIds($categoryIds, true, $object);
        } else {
            $categoryIds = $object->getAvailableInCategories();
            $categoryIds = $this->prepareCategoryIds($categoryIds, false, $object);
        }

        $op = $this->getOperatorForValidate();
        if ((($op == '==') || ($op == '!=')) && is_array($categoryIds)) {
            $value = preg_split('#\s*[,;]\s*#', (string)$this->getValueParsed(), 0, PREG_SPLIT_NO_EMPTY) ?: [];

            if ($this->registry->registry('apply_for_child_categories')) {
                foreach ($value as $categoryId) {
                    $category = $this->category->load($categoryId);
                    $children = preg_split('#\s*[,;]\s*#', $category->getAllChildren(), 0, PREG_SPLIT_NO_EMPTY);
                    $value    = array_merge($value, $children ?: []);
                }
            }

            $findElemInArray = array_intersect($categoryIds, $value);
            if (count($findElemInArray) > 0) {
                if ($op == '==') {
                    $result = true;
                }
                if ($op == '!=') {
                    $result = false;
                }
            } else {
                if ($op == '==') {
                    $result = false;
                }
                if ($op == '!=') {
                    $result = true;
                }
            }

            return $result;
        }

        return $this->validateAttribute($categoryIds);
    }

    /**
     * @param array                           $categoryIds
     * @param boolean                         $isCategory
     * @param Category $object
     *
     * @return array
     */
    protected function prepareCategoryIds($categoryIds, $isCategory, $object)
    {
        $applyForChildCategories = $this->getRule()->getApplyForChildCategories();
        if ($applyForChildCategories
            && $isCategory
            && ($categoryPath = $object->getPathIds())
            && is_array($categoryPath)) {
            $categoryIds = array_merge($categoryIds, $categoryPath);
        } elseif ($applyForChildCategories && !$isCategory) {
            /** @var \Traversable $categoryCollection */
            $categoryCollection = $object->getCategoryCollection();
            foreach ($categoryCollection as $category) {
                $categoryIds = array_merge($categoryIds, $category->getPathIds());
            }
        }

        return array_unique($categoryIds);
    }

    /**
     * @param AbstractModel $object
     * @param string                                 $attrCode
     *
     * @return bool
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    protected function validateValue($object, $attrCode)
    {
        if (!isset($this->entityAttributeValues[$object->getId()])) {
            $attr = $object->getResource()->getAttribute($attrCode);

            if ($attr && $attr->getBackendType() == 'datetime' && !is_int($this->getValue())) {
                $this->setValue(strtotime($this->getValue()));

                $value = strtotime($object->getData($attrCode));

                return $this->validateAttribute($value);
            }

            if ($attr && $attr->getFrontendInput() == 'multiselect') {
                $value = (string)$object->getData($attrCode);
                $value = strlen($value) ? explode(',', $value) : [];

                return $this->validateAttribute($value);
            }

            // we are here when categories validated by product attributes (layered navigation)

            $objectValue = $object->getData($attrCode) ? explode(',', $object->getData($attrCode)) : [];

            // compatibility with navigation multiselect
            if (count($objectValue) > 1 && !is_array($this->getValueParsed())) {
                $result = in_array($this->getValueParsed(), $objectValue);

                return strpos($this->getOperator(), '!') === false ? $result : !$result;
            }

            return parent::validate($object);
        } else {
            $result       = false; // any valid value will set it to TRUE
            $oldAttrValue = $object->hasData($attrCode) ? $object->getData($attrCode) : null;
            // remember old attribute state
            foreach ($this->entityAttributeValues[$object->getId()] as $value) {
                $attr = $object->getResource()->getAttribute($attrCode);
                if ($attr && $attr->getBackendType() == 'datetime') {
                    $value = strtotime($value);
                } elseif ($attr && $attr->getFrontendInput() == 'multiselect') {
                    $value = strlen($value) ? explode(',', $value) : [];
                }

                $object->setData($attrCode, $value);
                $result |= parent::validate($object);

                if ($result) {
                    break;
                }
            }

            if ($oldAttrValue === null) {
                $object->unsetData($attrCode);
            } else {
                $object->setData($attrCode, $oldAttrValue);
            }

            return (bool)$result;
        }
    }

    /**
     * @param Product $object
     *
     * @return bool
     */
    protected function validateQty($object)
    {
        $stockItem = $this->stockItemFactory->create()->setProduct($object);
        if ($object->getTypeId() == 'configurable' && $stockItem->getIsInStock()) {
            $requiredChildrenIds = $this->productTypeConfigurableFactory->create()
                ->getChildrenIds($object->getId(), true);
            $childrenIds         = [];
            foreach ($requiredChildrenIds as $groupedChildrenIds) {
                $childrenIds = array_merge($childrenIds, $groupedChildrenIds);
            }
            $sumQty = 0;
            foreach ($childrenIds as $childId) {
                $childQty = $this->stockState->getStockQty($childId);
                $sumQty   += $childQty;
            }

            return $this->validateAttribute($sumQty);
        } elseif ($object->getTypeId() == 'configurable') {
            return false;
        }

        $qty = $this->stockState->getStockQty($object->getId());

        return $this->validateAttribute($qty);
    }

    /**
     * @param array $attributes
     *
     * @return array
     */
    private function addSpecialAttributes(array $attributes)
    {
        $attributes['attribute_set_id'] = (string)__('Attribute set');
        $attributes['category_ids']     = (string)__('Category');
        $attributes['type_id']          = (string)__('Product type');

        return $attributes;
    }
}

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

namespace Mageplaza\ExtraFee\Model\ResourceModel;

use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Framework\Model\ResourceModel\Db\Context;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Class Rule
 * @package Mageplaza\ExtraFee\Model\ResourceModel
 */
class Rule extends AbstractDb
{
    /**
     * Date model
     *
     * @var DateTime
     */
    public $date;

    /** @var ResourceConnection */
    protected $resourceConnection;

    /** @var StoreManagerInterface */
    protected $storeManager;

    /** @var ProductCollectionFactory */
    protected $productCollectionFactory;

    /**
     * @var string
     */
    private $tableProductAttr = '';

    /**
     * Rule constructor.
     *
     * @param Context $context
     * @param DateTime $date
     * @param ResourceConnection $resourceConnection
     * @param StoreManagerInterface $storeManager
     * @param ProductCollectionFactory $productCollectionFactory
     */
    public function __construct(
        Context $context,
        DateTime $date,
        ResourceConnection $resourceConnection,
        StoreManagerInterface $storeManager,
        ProductCollectionFactory $productCollectionFactory
    ) {
        parent::__construct($context);

        $this->date                     = $date;
        $this->resourceConnection       = $resourceConnection;
        $this->storeManager             = $storeManager;
        $this->productCollectionFactory = $productCollectionFactory;

        $this->tableProductAttr = $this->resourceConnection
            ->getTableName('mageplaza_extrafee_product_attribute');
    }

    /**
     * Initialize resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init('mageplaza_extrafee_rule', 'rule_id');
    }

    /**
     * before save callback
     *
     * @param AbstractModel $object
     *
     * @return $this|AbstractDb
     */
    protected function _beforeSave(AbstractModel $object)
    {
        $object->setUpdatedAt($this->date->date());
        if ($object->isObjectNew()) {
            $object->setCreatedAt($this->date->date());
        }

        return $this;
    }

    /**
     * After save callback - persist product attribute mapping similar to salesrule_product_attribute
     *
     * @param AbstractModel $object
     *
     * @return $this
     */
    protected function _afterSave(AbstractModel $object)
    {
        // Save product attributes used in rule
        $ruleProductAttributes = array_merge(
            $this->getProductAttributes($this->getSerializer()->serialize($object->getConditions()->asArray())),
            $this->getProductAttributes($this->getSerializer()->serialize($object->getActions()->asArray()))
        );
        if (count($ruleProductAttributes)) {
            $this->setActualProductAttributes($object, $ruleProductAttributes);
        }

        return parent::_afterSave($object);
    }

    /**
     * Save product attributes currently used in conditions and actions of rule
     *
     * @param \Magento\SalesRule\Model\Rule $rule
     * @param mixed $attributes
     *
     * @return $this
     */
    public function setActualProductAttributes($rule, $attributes)
    {
        $connection = $this->getConnection();
        $connection->delete(
            $this->tableProductAttr,
            ['rule_id' . '=?' => $rule->getRuleId()]
        );

        //Getting attribute IDs for attribute codes
        $attributeIds    = [];
        $select          = $this->getConnection()->select()->from(
            ['a' => $this->getTable('eav_attribute')],
            ['a.attribute_id']
        )->where(
            'a.attribute_code IN (?)',
            [$attributes]
        );
        $attributesFound = $this->getConnection()->fetchAll($select);
        if ($attributesFound) {
            foreach ($attributesFound as $attribute) {
                $attributeIds[] = $attribute['attribute_id'];
            }

            $data = [];
            foreach ($this->convertToArray($rule->getData('customer_groups')) as $customerGroupId) {
                foreach ($this->convertToArray($rule->getData('store_ids')) as $websiteId) {
                    foreach ($attributeIds as $attribute) {
                        $data[] = [
                            'rule_id'           => $rule->getRuleId(),
                            'website_id'        => $websiteId,
                            'customer_group_id' => $customerGroupId,
                            'attribute_id'      => $attribute,
                        ];
                    }
                }
            }
            $connection->insertMultiple($this->tableProductAttr, $data);
        }

        return $this;
    }

    /**
     * @param $string
     *
     * @return string[]
     */
    private function convertToArray($string)
    {
        return explode(',', $string ?? '');
    }

    /**
     * Collect all product attributes used in serialized rule's action or condition
     *
     * @param string $serializedString
     *
     * @return array
     */
    public function getProductAttributes($serializedString)
    {
        // we need 4 backslashes to match 1 in regexp, see http://www.php.net/manual/en/regexp.reference.escape.php
        preg_match_all(
            '~"Magento\\\\\\\\SalesRule\\\\\\\\Model\\\\\\\\Rule\\\\\\\\Condition\\\\\\\\Product","attribute":"(.*?)"~',
            $serializedString,
            $matches
        );

        // we always have $matches like [[],[]]
        return array_values($matches[1]);
    }
}

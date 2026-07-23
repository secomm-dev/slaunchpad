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
 * @package     Mageplaza_OscUltimate
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\OscUltimate\Helper;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Newsletter\Model\Subscriber;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Mageplaza\Osc\Helper\Data as AbstractData;

/**
 * Class Data
 * @package Mageplaza\OscUltimate\Helper
 */
class Data extends AbstractData
{
    /**
     * @var string[]
     */
    public $sortedFields = [
        'addressInformation',
        'shippingMethod',
        'paymentMethod',
        'orderSummary'
    ];
    /**
     * @var string[]
     */
    public $sortedFields_2columns = [
        ["mp-col-1", ["addressInformation", "shippingMethod"]],
        ["mp-col-2", ["paymentMethod", 'orderSummary']]
    ];

    /**
     * @var string[]
     */
    public $sortedFields_2columns_floating = [
        'addressInformation',
        'shippingMethod',
        'paymentMethod',
    ];

    /**
     * @var string[]
     */
    public $sortedFields_3columns = [
        ["mp-col-1", ["addressInformation", "shippingMethod"]],
        ["mp-col-2", ["paymentMethod"]],
        ["mp-col-3", ['orderSummary']]
    ];
    /**
     * @var ResourceConnection
     */
    private $resourceConnection;

    /**
     * Data constructor.
     *
     * @param Context $context
     * @param ObjectManagerInterface $objectManager
     * @param StoreManagerInterface $storeManager
     * @param EncryptorInterface $encryptor
     * @param Json $json
     * @param Subscriber $subscriber
     * @param Session $checkoutSession
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(
        Context $context,
        ObjectManagerInterface $objectManager,
        StoreManagerInterface $storeManager,
        EncryptorInterface $encryptor,
        Json $json,
        Subscriber $subscriber,
        Session $checkoutSession,
        ResourceConnection $resourceConnection
    ) {
        $this->resourceConnection = $resourceConnection;
        parent::__construct($context, $objectManager, $storeManager, $encryptor, $json, $subscriber, $checkoutSession);
    }

    /**
     * @return string[]
     */
    public function getSortedFields()
    {
        $data = [];
        switch ($this->getSystemValue()) {
            case '1column':
            case '3columns-colspan':
                $data = $this->sortedFields;
                break;
            case '2columns':
                $data = $this->sortedFields_2columns;
                break;
            case '2columns-floating':
                $data = $this->sortedFields_2columns_floating;
                break;
            case '3columns':
                $data = $this->sortedFields_3columns;
                break;
        }

        return $data;
    }

    /**
     * @return string
     */
    public function getSystemValue()
    {
        $configFilePath = __DIR__ . '/../etc/config.xml';
        $xpath          = '/config/default/osc/design_configuration/page_layout';
        $configXml      = simplexml_load_file($configFilePath);

        return (string) $configXml->xpath($xpath)[0];
    }

    /**
     * @return array
     */

    public function getDataConfigValue($field, $scopeValue = null, $scopeType = ScopeInterface::SCOPE_STORES)
    {
        $connection  = $this->resourceConnection->getConnection();
        $configTable = $connection->getTableName("core_config_data");
        $select      = $connection->select()->from($configTable);

        $select->where('scope = ?', $scopeType)
            ->where('scope_id = ?', $scopeValue)
            ->Where('path = ?', $field);
        $result = $connection->fetchAll($select);

        return $result;
    }
}

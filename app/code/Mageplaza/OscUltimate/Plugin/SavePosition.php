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

namespace Mageplaza\OscUltimate\Plugin;

use Magento\Config\Model\ResourceModel\Config;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Store\Model\ScopeInterface;
use Mageplaza\Osc\Controller\Adminhtml\Field\Save;
use Mageplaza\Osc\Helper\Data;
use Mageplaza\OscUltimate\Helper\Data as OscUltimateHelper;

/**
 * Class SavePosition
 * @package Mageplaza\OscUltimate\Plugin
 */
class SavePosition
{
    /**
     * @var Config
     */
    private $resourceConfig;

    /**
     * @var JsonFactory
     */
    private $resultJsonFactory;
    /**
     * @var OscUltimateHelper
     */
    protected $oscHelper;

    /**
     * SavePosition constructor.
     *
     * @param Config $resourceConfig
     * @param JsonFactory $resultJsonFactory
     */
    public function __construct(
        Config $resourceConfig,
        JsonFactory $resultJsonFactory,
        OscUltimateHelper $oscHelper
    ) {
        $this->resourceConfig    = $resourceConfig;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->oscHelper         = $oscHelper;

    }

    /**
     * @param Save $subject
     *
     * @return Json|void
     */
    public function beforeExecute(Save $subject)
    {
        $manageBlock = OscUltimateHelper::jsonDecode($subject->getRequest()->getParam('manageBlock', false));
        $scopeId     = (int) ($manageBlock['ScopeId'] ?? 0);
        $layout      = $manageBlock['layout'];
         if ($manageBlock['useDefault']){
             if ($scopeId === 0) {
                 $this->resourceConfig->saveConfig(Data::CONFIG_DISPLAY_PAGE_LAYOUT,
                     $this->oscHelper->getSystemValue(),
                     ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
                     0);
                 $this->resourceConfig->deleteConfig(
                     Data::SORTED_BLOCK_POSITION,
                     ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
                     0
                 );
             } elseif ($scopeId < 0) {
                 $this->resourceConfig->deleteConfig(
                     Data::CONFIG_DISPLAY_PAGE_LAYOUT,
                     ScopeInterface::SCOPE_WEBSITES,
                     abs($scopeId)
                 );
                 $this->resourceConfig->deleteConfig(
                     Data::SORTED_BLOCK_POSITION,
                     ScopeInterface::SCOPE_WEBSITES,
                     abs($scopeId)
                 );
             }else {
                 $this->resourceConfig->deleteConfig(
                     Data::CONFIG_DISPLAY_PAGE_LAYOUT,
                     ScopeInterface::SCOPE_STORES,
                     $scopeId
                 );
                 $this->resourceConfig->deleteConfig(
                     Data::SORTED_BLOCK_POSITION,
                     ScopeInterface::SCOPE_STORES,
                     $scopeId
                 );
             }
         }else {
             if ($scopeId === 0) {
                 $this->resourceConfig->saveConfig(Data::CONFIG_DISPLAY_PAGE_LAYOUT,
                     $layout,
                     ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
                     0);
                 $this->resourceConfig->saveConfig(
                     Data::SORTED_BLOCK_POSITION,
                     OscUltimateHelper::jsonEncode($manageBlock),
                     ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
                     $scopeId
                 );
             } elseif ($scopeId < 0) {
                 $this->resourceConfig->saveConfig(Data::CONFIG_DISPLAY_PAGE_LAYOUT,
                     $layout,
                     ScopeInterface::SCOPE_WEBSITES,
                     abs($scopeId),
                     );
                 $this->resourceConfig->saveConfig(
                     Data::SORTED_BLOCK_POSITION,
                     OscUltimateHelper::jsonEncode($manageBlock),
                     ScopeInterface::SCOPE_WEBSITES,
                     abs($scopeId)
                 );
             }else {
                 $this->resourceConfig->saveConfig(Data::CONFIG_DISPLAY_PAGE_LAYOUT,
                     $manageBlock['layout'],
                     ScopeInterface::SCOPE_STORES,
                     $scopeId
                 );
                 $this->resourceConfig->saveConfig(
                     Data::SORTED_BLOCK_POSITION,
                     OscUltimateHelper::jsonEncode($manageBlock),
                     ScopeInterface::SCOPE_STORES,
                     $scopeId
                 );
             }
         }
    }
}


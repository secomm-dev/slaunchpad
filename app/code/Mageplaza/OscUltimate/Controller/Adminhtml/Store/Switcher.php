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

namespace Mageplaza\OscUltimate\Controller\Adminhtml\Store;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\NoSuchEntityException as NoSuchEntityExceptionAlias;
use Magento\Framework\View\LayoutFactory;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Mageplaza\OscUltimate\Block\Adminhtml\Layout\ManageBlock\Column;
use Mageplaza\OscUltimate\Block\Adminhtml\Layout\ManageBlock\Columns;
use Mageplaza\OscUltimate\Block\Adminhtml\Layout\ManageBlock\ColumnsColspan;
use Mageplaza\OscUltimate\Block\Adminhtml\Layout\ManageBlock\ColumnsFloat;
use Mageplaza\OscUltimate\Block\Adminhtml\Layout\ManageBlock\ThreeColumns;
use Mageplaza\OscUltimate\Helper\Data as OscHelper;

/**
 * Class Switcher
 * @package Mageplaza\OscUltimate\Controller\Adminhtml\Store
 */
class Switcher extends Action
{
    /**
     * @var LayoutFactory
     */
    protected $layoutFactory;

    /**
     * @var JsonFactory
     */
    private $resultJsonFactory;

    /**
     * @var OscHelper
     */
    protected $oscHelper;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * Switcher constructor.
     *
     * @param Context $context
     * @param LayoutFactory $layoutFactory
     * @param JsonFactory $resultJsonFactory
     * @param OscHelper $oscHelper
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        Context $context,
        LayoutFactory $layoutFactory,
        JsonFactory $resultJsonFactory,
        OscHelper $oscHelper,
        StoreManagerInterface $storeManager
    ) {
        $this->layoutFactory     = $layoutFactory;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->oscHelper         = $oscHelper;
        $this->storeManager      = $storeManager;
        parent::__construct($context);
    }

    /**
     * @return ResponseInterface|Json|ResultInterface
     * @throws NoSuchEntityExceptionAlias
     */
    public function execute()
    {
        /** @var Json $resultJson */
        $resultJson = $this->resultJsonFactory->create();
        $useDefaultResponse = 0;
        $layout = $this->layoutFactory->create();
        $scopeId = (int) $this->getRequest()->getParam('id', false);
        $useDefault = $this->getRequest()->getParam('useDefault', false);
        $pageLayout = null;
        if ($useDefault === 'true') {
            if ($scopeId === 0) {
                $pageLayout = $this->oscHelper->getSystemValue();
            } elseif ($scopeId < 0) {
                $pageLayout = $this->oscHelper->getConfigValue(OscHelper::CONFIG_DISPLAY_PAGE_LAYOUT);
            } else {
                $websiteId = $this->storeManager->getStore($scopeId)->getWebsiteId();
                $pageLayout = $this->oscHelper->getConfigValue(OscHelper::CONFIG_DISPLAY_PAGE_LAYOUT, $websiteId,
                    ScopeInterface::SCOPE_WEBSITES);
                if (!$pageLayout) {
                    $pageLayout = $this->oscHelper->getConfigValue(OscHelper::CONFIG_DISPLAY_PAGE_LAYOUT);
                }
            }
        } else {
            if ($scopeId === 0) {
                $pageLayout = $this->oscHelper->getConfigValue(OscHelper::CONFIG_DISPLAY_PAGE_LAYOUT);
                if(!$this->oscHelper->getConfigValue(OscHelper::SORTED_BLOCK_POSITION)){
                    $useDefaultResponse = 1;
                }
            } elseif ($scopeId < 0) {
                $result = $this->oscHelper->getDataConfigValue(OscHelper::CONFIG_DISPLAY_PAGE_LAYOUT,
                    abs($scopeId), ScopeInterface::SCOPE_WEBSITES);
                if ($result){
                    $pageLayout =$this->oscHelper->getConfigValue(OscHelper::CONFIG_DISPLAY_PAGE_LAYOUT,
                        abs($scopeId), ScopeInterface::SCOPE_WEBSITES);
                }
            } else {
                $result = $this->oscHelper->getDataConfigValue(OscHelper::CONFIG_DISPLAY_PAGE_LAYOUT, $scopeId,
                    ScopeInterface::SCOPE_STORES);
                if ($result) {
                    $pageLayout = $this->oscHelper->getConfigValue(OscHelper::CONFIG_DISPLAY_PAGE_LAYOUT, $scopeId,
                        ScopeInterface::SCOPE_STORES);
                }
            }
            if (!$pageLayout) {
                if ($scopeId === 0) {
                    $pageLayout = $this->oscHelper->getSystemValue();
                } elseif ($scopeId < 0) {
                    $pageLayout = $this->oscHelper->getConfigValue(OscHelper::CONFIG_DISPLAY_PAGE_LAYOUT);
                } else {
                    $websiteId = $this->storeManager->getStore($scopeId)->getWebsiteId();
                    $pageLayout = $this->oscHelper->getConfigValue(OscHelper::CONFIG_DISPLAY_PAGE_LAYOUT, $websiteId,
                        ScopeInterface::SCOPE_WEBSITES,);
                    if (!$pageLayout) {
                        $pageLayout = $this->oscHelper->getConfigValue(OscHelper::CONFIG_DISPLAY_PAGE_LAYOUT);
                    }
                }
                $useDefaultResponse = 1;
            }
        }

        if (!$pageLayout) {
            $useDefaultResponse = 1;
            $pageLayout = $this->oscHelper->getSystemValue();
        }
        switch ($pageLayout) {
            case '1column':
                $block = $layout->createBlock(Column::class);
                break;
            case '2columns':
                $block = $layout->createBlock(Columns::class);
                break;
            case '2columns-floating':
                $block = $layout->createBlock(ColumnsFloat::class);
                break;
            case '3columns':
                $block = $layout->createBlock(ThreeColumns::class);
                break;
            case '3columns-colspan':
                $block = $layout->createBlock(ColumnsColspan::class);
                break;
        }

        if ($useDefaultResponse) {
            $this->getRequest()->setParam('useDefault', 'true');
        }
        $data = [
            'layout'     => $pageLayout,
            'block_html' => $block->toHtml(),
            'useDefaultResponse' => $useDefaultResponse
        ];

        return $resultJson->setData($data);
    }
}

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

namespace Mageplaza\OscUltimate\Block\Adminhtml\Layout;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Widget\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Mageplaza\OscUltimate\Helper\Data as OscHelper;

/**
 * Class AbstractBlock
 * @package Mageplaza\OscUltimate\Block\Adminhtml\Layout
 */
abstract class AbstractBlock extends Template
{
    const BLOCK_ID = '';

    /**
     * @var string
     */
    protected $_template = 'Mageplaza_OscUltimate::block/manageBlock.phtml';

    /**
     * @var OscHelper
     */
    protected $helper;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * AbstractBlock constructor.
     *
     * @param Context $context
     * @param OscHelper $helper
     * @param StoreManagerInterface $storeManager
     * @param array $data
     */
    public function __construct(
        Context $context,
        OscHelper $helper,
        StoreManagerInterface $storeManager,
        array $data = []
    ) {
        $this->helper       = $helper;
        $this->storeManager = $storeManager;
        parent::__construct($context, $data);
    }

    /**
     * @return string
     */
    public function getBlockId()
    {
        return static::BLOCK_ID;
    }

    /**
     * @return OscHelper
     */
    public function getHelperData()
    {
        return $this->helper;
    }

    /**
     * @return bool
     */
    public function isVisible()
    {
        return true;
    }

    /**
     * @return array|mixed|null
     */
    public function getStore()
    {
        return $this->getData('id');
    }


    /**
     * @return array
     * @throws NoSuchEntityException
     */
    public function getDataSortedFields()
    {
        $data        = $this->getRequest()->getParams();
        $manageBlock = [];
        if (array_key_exists('useDefault', $data)) {
            $scopeId = $data['id'] ?? 0;
            if ($data['useDefault'] === 'true') {
                if ($scopeId === 0) {
                    $manageBlock['data']   = $this->helper->getSortedFields();
                    $manageBlock['layout'] = $this->helper->getSystemValue();
                } elseif ($scopeId < 0) {
                    $manageBlock = OscHelper::jsonDecode((string) $this->helper->getConfigValue
                    (OscHelper::SORTED_BLOCK_POSITION));
                } else {
                    $websiteId   = $this->storeManager->getStore($scopeId)->getWebsiteId();
                    $manageBlock = OscHelper::jsonDecode((string) $this->helper->getConfigValue(OscHelper::SORTED_BLOCK_POSITION,
                        $websiteId,
                        ScopeInterface::SCOPE_WEBSITES));
                }
            } else {
                if ($scopeId === 0) {
                    $manageBlock = OscHelper::jsonDecode((string) $this->helper->getConfigValue(OscHelper::SORTED_BLOCK_POSITION,
                        ScopeConfigInterface::SCOPE_TYPE_DEFAULT));
                } elseif ($scopeId < 0) {
                    $manageBlock = OscHelper::jsonDecode((string) $this->helper->getConfigValue(OscHelper::SORTED_BLOCK_POSITION,
                        abs($scopeId),
                        ScopeInterface::SCOPE_WEBSITES));
                } else {
                    $manageBlock = OscHelper::jsonDecode((string) $this->helper->getConfigValue(OscHelper::SORTED_BLOCK_POSITION,
                        $scopeId,
                        ScopeInterface::SCOPE_STORE)
                    );
                }
            }
        }

        return $manageBlock;
    }
}

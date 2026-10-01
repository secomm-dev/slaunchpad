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
 * @package     Mageplaza_RMA
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\RMA\Block\Adminhtml\Request\Edit\Form;

use Magento\Backend\Block\Template\Context;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\System\Store as SystemStore;
use Mageplaza\RMA\Block\Adminhtml\Request\Edit\Request;

/**
 * Class Template
 * @method string getTemplateStoreId()
 * @method Template setTemplateCollection($templateCollection)
 * @method Template setTemplateStoreId($templateStoreId)
 * @method Template setTemplateModel($templateModel)
 * @package Mageplaza\RMA\Block\Adminhtml\Request\Edit\Form
 */
class Template extends Request
{
    /**
     * @var SystemStore
     */
    protected $_systemStore;

    /**
     * Template constructor.
     *
     * @param Context $context
     * @param SystemStore $systemStore
     * @param array $data
     */
    public function __construct(
        Context $context,
        SystemStore $systemStore,
        array $data = []
    ) {
        $this->_systemStore = $systemStore;

        parent::__construct($context, $data);
    }

    /**
     * @return array
     */
    public function getStoreStructure()
    {
        return $this->_systemStore->getStoreValuesForForm(false, true);
    }

    /**
     * @param string $templateId
     *
     * @return string
     */
    public function getTemplateEditUrl($templateId)
    {
        return $this->getUrl('mprma/request/template_load', [
            'form_key' => $this->getFormKey(),
            'template_type' => self::TEMPLATE_EDIT_PAGE,
            'template_id' => $templateId
        ]);
    }

    /**
     * @return string
     */
    public function getInsertTemplateUrl()
    {
        return $this->getUrl('mprma/request/template_insert', ['form_key' => $this->getFormKey()]);
    }

    /**
     * @return string
     */
    public function getDeleteTemplateUrl()
    {
        return $this->getUrl('mprma/request/template_delete', ['form_key' => $this->getFormKey()]);
    }

    /**
     * @param int $storeId
     *
     * @return string
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function getStoreViewHtml($storeId)
    {
        if ($storeId === 0) {
            return '<b class="mp-store-view">(' . __('All Store Views') . ')</b>';
        }
        $store = $this->_storeManager->getStore($storeId);
        $storeName = $store->getName();
        $groupName = $this->_storeManager->getGroup($store->getStoreGroupId())->getName();
        $websiteName = $this->_storeManager->getWebsite($store->getWebsiteId())->getName();

        return '<b class="mp-store-view">(' . $websiteName . ' > ' . $groupName . ' > ' . $storeName . ')</b>';
    }
}

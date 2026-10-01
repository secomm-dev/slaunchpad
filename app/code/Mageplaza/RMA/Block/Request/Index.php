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

namespace Mageplaza\RMA\Block\Request;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Sales\Model\Order;
use Mageplaza\RMA\Block\Request;
use Mageplaza\RMA\Helper\Data as HelperData;
use Mageplaza\RMA\Helper\Image as HelperImage;
use Mageplaza\RMA\Model\Config\Source\RMARequest\FindOrder;
use Mageplaza\RMA\Model\Config\Source\RMARequest\Orders;

/**
 * Class Index
 * @package Mageplaza\RMA\Block\Request
 */
class Index extends Request
{
    /**
     * @var Orders
     */
    protected $_orderToArray;

    /**
     * @return Template
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    protected function _prepareLayout()
    {
        if (!$this->_helperData->getRequestConfig('google_recaptcha')) {
            $this->getLayout()->unsetElement('mageplaza.recaptcha.load');
        }

        return parent::_prepareLayout();
    }

    /**
     * Index constructor.
     *
     * @param Context $context
     * @param Registry $coreRegistry
     * @param HelperData $helperData
     * @param HelperImage $helperImage
     * @param FindOrder $findOrderBy
     * @param Orders $orderToArray
     * @param CustomerSession $customerSession
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $coreRegistry,
        HelperData $helperData,
        HelperImage $helperImage,
        FindOrder $findOrderBy,
        Orders $orderToArray,
        CustomerSession $customerSession,
        array $data = []
    ) {
        $this->_orderToArray = $orderToArray;

        parent::__construct(
            $context,
            $coreRegistry,
            $helperData,
            $helperImage,
            $findOrderBy,
            $customerSession,
            $data
        );
    }

    /**
     * Retrieve current order model instance
     *
     * @return Order
     */
    public function getOrder()
    {
        return $this->_coreRegistry->registry('current_order');
    }

    /**
     * @return mixed
     */
    public function isCustomerRequest()
    {
        return $this->_coreRegistry->registry('is_customer_request');
    }

    /**
     * @return string
     */
    public function getUploadUrl()
    {
        return $this->getUrl('mprma/request/upload');
    }

    /**
     * @return string
     */
    public function getLoadOrderInformationUrl()
    {
        return $this->getUrl('mprma/request/order_load');
    }

    /**
     * @return string
     */
    public function getRequestSaveUrl()
    {
        return $this->getUrl('mprma/request/save');
    }

    /**
     * @return string
     */
    public function getBackToFormUrl()
    {
        return $this->getUrl('mprma/request/form');
    }

    /**
     * @return string
     */
    public function isUploadFiles()
    {
        return $this->_helperData->getRequestConfig('allow_attachment');
    }

    /**
     * @return array
     */
    public function getAvailableOrders()
    {
        $customerId = $this->_helperData->getCustomerId() ?: 0;

        return $this->_orderToArray->toAvailableOptionArray($customerId);
    }
}

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

namespace Mageplaza\RMA\Block;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Mageplaza\RMA\Helper\Data as HelperData;
use Mageplaza\RMA\Helper\Image as HelperImage;
use Mageplaza\RMA\Model\Config\Source\RMARequest\FindOrder;

/**
 * Class Request
 * @package Mageplaza\RMA\Block
 */
class Request extends Template
{
    /**
     * @var Registry
     */
    protected $_coreRegistry;

    /**
     * @var HelperImage
     */
    protected $_helperImage;

    /**
     * @var FindOrder
     */
    protected $_findOrderBy;

    /**
     * @var HelperData
     */
    public $_helperData;

    /**
     * @var CustomerSession
     */
    protected $_customerSession;

    /**
     * Request constructor.
     *
     * @param Context $context
     * @param Registry $coreRegistry
     * @param HelperData $helperData
     * @param HelperImage $helperImage
     * @param FindOrder $findOrderBy
     * @param CustomerSession $customerSession
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $coreRegistry,
        HelperData $helperData,
        HelperImage $helperImage,
        FindOrder $findOrderBy,
        CustomerSession $customerSession,
        array $data = []
    ) {
        $this->_coreRegistry         = $coreRegistry;
        $this->_helperData           = $helperData;
        $this->_helperImage          = $helperImage;
        $this->_findOrderBy          = $findOrderBy;
        $this->_customerSession      = $customerSession;

        parent::__construct($context, $data);
    }

    /**
     * @return Template
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    protected function _prepareLayout()
    {
        if ($breadcrumbs = $this->getLayout()->getBlock('breadcrumbs')) {
            $breadcrumbs->addCrumb('home', [
                'label' => __('Home'),
                'title' => __('Go to Home Page'),
                'link'  => $this->_storeManager->getStore()->getBaseUrl()
            ]);
            $breadcrumbs->addCrumb('cms_page', [
                'label' => __('Order Information'),
                'title' => __('Order Information')
            ]);
        }

        return parent::_prepareLayout();
    }

    /**
     * @return array
     */
    public function getFindOrderTypes()
    {
        return $this->_findOrderBy->toOptionArray();
    }

    /**
     * @return boolean
     */
    public function checkLogin()
    {
        return $this->_customerSession->isLoggedIn();
    }

    /**
     * @return string
     */
    public function getEmail()
    {
        return $this->_customerSession->getCustomer()->getData('email');
    }

    /**
     * @return string
     */
    public function getLastName(){

        return $this->_customerSession->getCustomer()->getData('lastname');
    }
}

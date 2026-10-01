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

use DateTime;
use Exception;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Directory\Model\Currency;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Filesystem\Io\File;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Store\Model\StoreManagerInterface;
use Mageplaza\RMA\Block\Request;
use Mageplaza\RMA\Helper\Data as HelperData;
use Mageplaza\RMA\Helper\Image as HelperImage;
use Mageplaza\RMA\Model\Config\Source\RMARequest\FindOrder;
use Mageplaza\RMA\Model\Request as RequestModel;

/**
 * Class Form
 * @package Mageplaza\RMA\Block\Request
 */
class View extends Request
{
    /**
     * @var File
     */
    protected $_ioFile;

    /**
     * @var Currency
     */
    protected $_currency;

    /**
     * @var StoreManagerInterface
     */
    protected $_storeManagerInterface;

    /**
     * View constructor.
     *
     * @param Context $context
     * @param Registry $coreRegistry
     * @param File $ioFile
     * @param Currency $currency
     * @param StoreManagerInterface $storeManagerInterface
     * @param HelperData $helperData
     * @param HelperImage $helperImage
     * @param FindOrder $findOrderBy
     * @param CustomerSession $customerSession
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $coreRegistry,
        File $ioFile,
        Currency $currency,
        StoreManagerInterface $storeManagerInterface,
        HelperData $helperData,
        HelperImage $helperImage,
        FindOrder $findOrderBy,
        CustomerSession $customerSession,
        array $data = []
    ) {
        $this->_ioFile   = $ioFile;
        $this->_currency = $currency;
        $this->_storeManagerInterface = $storeManagerInterface;

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
     * @return Template
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    protected function _prepareLayout()
    {
        $this->pageConfig->getTitle()->set($this->getRmaRequest()->getIncrementId());

        return parent::_prepareLayout();
    }

    /**
     * @return RequestModel
     */
    public function getRmaRequest()
    {
        return $this->_coreRegistry->registry('mageplaza_current_rma_request');
    }

    /**
     * @param string $date
     *
     * @return DateTime
     * @throws Exception
     */
    public function getConvertedDate($date)
    {
        return $this->_helperData->getConvertedDate($date);
    }

    /**
     * @param string $file
     * @param bool $isTmp
     *
     * @return mixed|string
     * @throws NoSuchEntityException
     */
    public function getFileImageUrl($file, $isTmp)
    {
        $pathInfo = $this->_ioFile->getPathInfo($file);

        return $this->_helperImage->getFileImageUrl($file, $pathInfo, $isTmp);
    }

    /**
     * @param string|int $requestId
     *
     * @return string
     */
    public function getReplyFormAction($requestId)
    {
        return $this->getUrl('mprma/request/reply_save', ['request_id' => $requestId]);
    }

    /**
     * @return string
     */
    public function getUploadReplyUrl()
    {
        return $this->getUrl('mprma/request/reply_upload');
    }

    /**
     * @return string
     */
    public function getLoadReplyUrl()
    {
        return $this->getUrl('mprma/request/reply_load');
    }

    /**
     * @return string
     */
    public function getSaveReplyUrl()
    {
        return $this->getUrl('mprma/request/reply_save');
    }

    /**
     * @return string
     */
    public function getCustomerDashboardUrl()
    {
        return $this->getUrl('mprma/customer');
    }

    /**
     * @param string $requestId
     *
     * @return string
     */
    public function getPrintShippingLabelUrl($requestId)
    {
        return $this->getUrl('mprma/request/shippingLabel', ['request_id' => $requestId]);
    }

    /**
     * @param $price
     * @param $currencyCode
     * @return float
     * @throws Exception
     */
    public function getCurrencyPrice($price, $currencyCode)
    {
        $currency = $this->_currency->load('USD');
        return $currency->convert($price, $currencyCode);
    }

    /**
     * @return mixed
     * @throws NoSuchEntityException
     */
    public function getCurrentCurrency()
    {
        return $this->_storeManagerInterface->getStore()->getCurrentCurrency()->getCurrencyCode();
    }
}

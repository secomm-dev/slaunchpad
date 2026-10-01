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

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Address\Renderer;
use Mageplaza\RMA\Helper\Data;
use Mageplaza\RMA\Helper\Image;
use Mageplaza\RMA\Model\Config\Source\RMAShippingLabel\BarcodeType;
use Mageplaza\RMA\Model\Request as RequestModel;
use Mageplaza\RMA\Model\Request\Item;
use Mageplaza\RMA\Model\RequestFactory;
use Mageplaza\RMA\Model\ResourceModel\Request as RequestResource;
use Mageplaza\RMA\Model\ShippingLabel as ShippingLabelModel;
use Picqer\Barcode\BarcodeGeneratorPNG;
use Picqer\Barcode\Exceptions\BarcodeException;

/**
 * Class ShippingLabel
 * @package Mageplaza\RMA\Block\Request
 */
class ShippingLabel extends Template
{
    /**
     * @var Renderer
     */
    protected $_renderAddress;

    /**
     * @var RequestModel
     */
    protected $_rmaRequest;

    /**
     * @var RequestFactory
     */
    protected $_rmaRequestFact;

    /**
     * @var RequestResource
     */
    protected $_rmaRequestResource;

    /**
     * @var Image
     */
    protected $_helperImage;

    /**
     * @var Data
     */
    public $helperData;

    /**
     * ShippingLabel constructor.
     *
     * @param Context $context
     * @param Renderer $renderAddress
     * @param RequestFactory $rmaRequestFact
     * @param RequestResource $rmaRequestResource
     * @param Image $helperImage
     * @param Data $helperData
     * @param array $data
     */
    public function __construct(
        Context $context,
        Renderer $renderAddress,
        RequestFactory $rmaRequestFact,
        RequestResource $rmaRequestResource,
        Image $helperImage,
        Data $helperData,
        array $data = []
    ) {
        $this->_renderAddress = $renderAddress;
        $this->_rmaRequestFact = $rmaRequestFact;
        $this->_rmaRequestResource = $rmaRequestResource;
        $this->_helperImage = $helperImage;
        $this->helperData = $helperData;

        parent::__construct($context, $data);
    }

    /**
     * @return RequestModel
     */
    public function getRmaRequest()
    {
        $requestId = $this->_request->getParam('request_id');
        if (!$this->_rmaRequest) {
            $this->_rmaRequest = $this->_rmaRequestFact->create();
            $this->_rmaRequestResource->load($this->_rmaRequest, $requestId);
        }

        return $this->_rmaRequest;
    }

    /**
     * @param Order $order
     *
     * @return string|null
     */
    public function renderBillingAddress($order)
    {
        return $this->_renderAddress->format($order->getShippingAddress(), 'html');
    }

    /**
     * @param RequestModel $request
     *
     * @return string
     */
    public function renderRequestItems($request)
    {
        $itemNames = [];
        foreach ($request->getItemsCollection() as $item) {
            /** @var Item $item */
            $itemNames[] = $item->getName();
        }

        return implode(',', $itemNames);
    }

    /**
     * @param string $image
     * @param string $type
     *
     * @return string
     * @throws NoSuchEntityException
     */
    public function getShippingLabelImageUrl($image, $type = Image::TEMPLATE_MEDIA_TYPE_SHIPPING_LABEL)
    {
        $imageFile = $this->_helperImage->getMediaPath($image, $type);

        return $this->_helperImage->getMediaUrl($imageFile);
    }

    /**
     * @param RequestModel $request
     *
     * @return string
     * @throws BarcodeException
     */
    public function generateBarcode($request)
    {
        $shippingLabel = $request->getShippingLabel();
        $generator = new BarcodeGeneratorPNG();
        $barcode = $request->getIncrementId();
        if ((int)$shippingLabel->getBarcode() === BarcodeType::ORDER_INCREMENT_ID) {
            $barcode = $request->getOrder()->getIncrementId();
        }
        $barcodeData = $generator->getBarcode($barcode, $generator::TYPE_CODE_128);

        return '<img src="data:image/png;base64,' . base64_encode($barcodeData) . '" alt="' . __('Barcode') . '">'
            . '<p>' . $barcode . '</p>';
    }

    /**
     * @param ShippingLabelModel $shippingLabel
     *
     * @return array
     */
    public function getShippingLabelInformation($shippingLabel)
    {
        return explode(',', $shippingLabel->getInformation());
    }
}

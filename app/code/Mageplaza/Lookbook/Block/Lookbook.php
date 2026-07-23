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
 * @package     Mageplaza_Lookbook
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\Lookbook\Block;

use Magento\Catalog\Helper\Image as CatalogImage;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ProductFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\Template;
use Magento\Widget\Block\BlockInterface;
use Mageplaza\Lookbook\Block\Widget;
use Mageplaza\Lookbook\Helper\Data;
use Mageplaza\Lookbook\Model\Config\Source\MarkerType;
use Mageplaza\Lookbook\Model\LookbookFactory;
use Magento\Catalog\Block\Product\AbstractProduct;

/**
 * Class Lookbook
 * @package Mageplaza\Lookbook\Block
 */
class Lookbook extends Template implements BlockInterface
{
    protected $_template = 'Mageplaza_Lookbook::lookbook.phtml';
    /**
     * @var LookbookFactory
     */
    protected $lookbookFactory;
    /**
     * @var Data
     */
    protected $helperData;
    /**
     * @var \Mageplaza\Lookbook\Model\Lookbook
     */
    protected $_lookbook;
    /**
     * @var CatalogImage
     */
    protected $catalogImage;
    /**
     * @var ProductFactory
     */
    private $productFactory;

    /**
     * @var Widget
     */
    protected $widget;

    /**
     * Lookbook constructor.
     *
     * @param Template\Context $context
     * @param ProductFactory $productFactory
     * @param CatalogImage $catalogImage
     * @param LookbookFactory $lookbookFactory
     * @param Data $helperData
     * @param Widget $widget
     * @param array $data
     */
    public function __construct(
        Template\Context $context,
        ProductFactory $productFactory,
        CatalogImage $catalogImage,
        LookbookFactory $lookbookFactory,
        Data $helperData,
        Widget $widget,
        array $data = []
    ) {
        $this->productFactory  = $productFactory;
        $this->catalogImage    = $catalogImage;
        $this->lookbookFactory = $lookbookFactory;
        $this->helperData      = $helperData;
        $this->widget          = $widget;

        parent::__construct($context, $data);
    }

    /**
     * @return string|null
     */
    public function isLazyLoad()
    {
        if ($this->hasData('isLazyLoad')) {
            return $this->getData('isLazyLoad');
        }

        return '0';
    }

    /**
     * @return string
     * @throws NoSuchEntityException
     */
    public function getMarkerStyle()
    {
        $width = $this->helperData->getMarkerWidth() . 'px';
        $height = $this->helperData->getMarkerHeight() . 'px';
        $url = $this->getMarkerIconUrl();
        if ($this->widget->isHyvaTheme()) {
            return 'width:' . $width . ';height:' . $height . ';background:url(' . $url . ') no-repeat; background-size:' . $width . ' ' . $height . '; position: absolute; cursor: pointer; opacity: 1;';
        }

        return 'style="width:' . $width . ';height:' . $height . ';background:url(' . $url . ') no-repeat; background-size:' . $width . ' ' . $height . ' "';
    }

    /**
     * @return string
     * @throws NoSuchEntityException
     */
    public function getMarkerHeight()
    {
        return $this->helperData->getMarkerHeight();
    }

    /**
     * @return string
     * @throws NoSuchEntityException
     */
    public function getMarkerIconUrl()
    {
        $params = [
            'area' => 'frontend'
        ];

        return $this->helperData->getMarkerIcon() ?:
            $this->_assetRepo->createAsset('Mageplaza_Lookbook::js/plugins/icon/marker.png', $params)->getUrl();
    }

    /**
     * @param string $imageSrc
     *
     * @return string
     */
    public function getImageUrl($imageSrc)
    {
        return $this->helperData->getBaseImageUrl() . $imageSrc;
    }

    /**
     * @return array|string
     * @throws LocalizedException
     */
    public function getMarkerData()
    {
        $lookbook = $this->getLookbook();
        if (!$lookbook) {
            return '';
        }
        $markerData = Data::jsonDecode($lookbook->getMarker());
        if (!isset($markerData['mplookbook_pin']) || empty($markerData['mplookbook_pin'])) {
            return '';
        }

        $data = [];
        foreach ($markerData['mplookbook_pin'] as $key => $value) {
            if ($key === 'canvas') {
                $data[$key] = $value;
            }
            if (isset($value['wts'], $value['product_id']) && $value['wts'] === 'product') {
                if ($this->isPopupType()) {
                    $product = $this->getProductData($value['product_id']);
                    $productUrl = $product->getProductUrl();
                    $data[$key]['template'] = '<a class="mplookbook-quickview" data-quickview-url=' . $productUrl . ' href="javascript:void(0);"></a>';
                } else {
                    $data[$key]['template'] = $this->getProductTemplate($value['product_id']);
                }

                $data[$key]['coords'] = $value['coords'];
            }
            if (isset($value['wts'], $value['template']) && $value['wts'] === 'custom') {
                if ($this->isPopupType()) {
                    $data[$key]['template'] = '<div class="white-popup">' . $value['template'] . '</div>';
                } else {
                    $data[$key]['template'] = '<div class="popBg borderRadius"></div><div class="mplookbook-popBody">' . $value['template'] . '</div>';
                }
                $data[$key]['coords'] = $value['coords'];
            }
        }

        return $data;
    }

    /**
     * @return array|\Mageplaza\Lookbook\Model\Lookbook|null
     */
    public function getLookbook()
    {
        if (!$this->helperData->isEnabled()) {
            return null;
        }

        if ($this->_lookbook !== null) {
            return $this->_lookbook;
        }

        if ($this->hasData('lookbook')) {
            $this->_lookbook = $this->getData('lookbook');
        } elseif ($this->hasData('lookbook_id')) {
            $this->_lookbook = $this->lookbookFactory->create()->load($this->getData('lookbook_id'));
        }

        return $this->_lookbook;
    }

    /**
     * @return bool
     */
    public function isPopupType()
    {
        $lookbook = $this->getLookbook();

        return $lookbook->getMarkerType() === MarkerType::POPUP;
    }

    /**
     * @param string|int $productId
     *
     * @return mixed
     * @throws LocalizedException
     */
    public function getProductTemplate($productId)
    {
        $product = $this->getProductData($productId);

        return $this->getLayout()->createBlock(AbstractProduct::class)
            ->setData('product', $product)
            ->setTemplate('Mageplaza_Lookbook::marker/product.phtml')
            ->toHtml();
    }

    /**
     * @param string|int $productId
     *
     * @return Product
     */
    protected function getProductData($productId)
    {
        return $this->productFactory->create()->load($productId);
    }

    /**
     * Get relevant path to template
     *
     * @return string
     */
    public function getTemplate()
    {
        if ($this->widget->isHyvaTheme())  {
            return 'Mageplaza_Lookbook::hyva/lookbook.phtml';
        }

        return parent::getTemplate();
    }
}

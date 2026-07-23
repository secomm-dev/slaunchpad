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
 * @package     Mageplaza_ExtraFee
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\ExtraFee\Model\Sales\Order\Pdf;

use Magento\Framework\App\ObjectManager;
use Magento\Framework\Locale\ResolverInterface;
use Magento\Sales\Model\Order\Pdf\Shipment as ShipmentPdf;
use Magento\Sales\Model\Order\Shipment as ShipmentModel;
use Magento\Sales\Model\Order\Shipment\Item;
use Magento\Store\Model\ScopeInterface;
use Mageplaza\ExtraFee\Helper\Data;
use Mageplaza\ExtraFee\Model\Config\Source\DisplayArea;
use Zend_Pdf;
use Zend_Pdf_Color_GrayScale;
use Zend_Pdf_Color_Rgb;
use Zend_Pdf_Exception;
use Zend_Pdf_Page;
use Zend_Pdf_Style;

/**
 * Class Shipment
 * @package Mageplaza\ExtraFee\Model\Sales\Order\Pdf
 */
class Shipment extends ShipmentPdf
{
    /**
     * @var Data
     */
    protected $helper;

    /**
     * @var ResolverInterface
     */
    protected $_localeResolver;

    /**
     * @param array $shipments
     *
     * @return Zend_Pdf
     * @throws Zend_Pdf_Exception
     */
    public function getPdf($shipments = [])
    {
        $this->_beforeGetPdf();
        $this->_initRenderer('shipment');

        $pdf = new Zend_Pdf();
        $this->_setPdf($pdf);
        $style = new Zend_Pdf_Style();
        $this->_setFontBold($style, 10);

        $objectManager         = ObjectManager::getInstance();
        $this->helper          = $objectManager->create(Data::class);
        $this->_localeResolver = $objectManager->create(ResolverInterface::class);

        foreach ($shipments as $shipment) {
            /** @var ShipmentModel $shipment */
            if ($shipment->getStoreId()) {
                $this->_localeResolver->emulate($shipment->getStoreId());
                $this->_storeManager->setCurrentStore($shipment->getStoreId());
            }
            $page  = $this->newPage();
            $order = $shipment->getOrder();
            /* Add image */
            $this->insertLogo($page, $shipment->getStore());
            /* Add address */
            $this->insertAddress($page, $shipment->getStore());
            /* Add head */
            $this->insertOrder(
                $page,
                $shipment,
                $this->_scopeConfig->isSetFlag(
                    self::XML_PATH_SALES_PDF_SHIPMENT_PUT_ORDER_ID,
                    ScopeInterface::SCOPE_STORE,
                    $order->getStoreId()
                )
            );

            if ($this->helper->isEnabled()) {
                $this->insertMpExtraFee($page, $order);
            }

            /* Add document text and number */
            $this->insertDocumentNumber($page, __('Packing Slip # ') . $shipment->getIncrementId());
            /* Add table */
            $this->_drawHeader($page);
            /* Add body */
            foreach ($shipment->getAllItems() as $item) {
                /** @var Item $item */
                if ($item->getOrderItem()->getParentItem()) {
                    continue;
                }
                /* Draw item */
                $this->_drawItem($item, $page, $order);
                $page = end($pdf->pages);
            }
        }
        $this->_afterGetPdf();
        if ($shipment->getStoreId()) {
            $this->_localeResolver->revert();
        }

        return $pdf;
    }

    /**
     * @param Zend_Pdf_Page $page
     * @param $order
     *
     * @throws Zend_Pdf_Exception
     */
    public function insertMpExtraFee(&$page, $order)
    {
        $extraFeeTotal = $this->helper->getExtraFeeTotals($order);
        if (!count($extraFeeTotal)) {
            return;
        }

        $this->y += 8;
        $page->setFillColor(new Zend_Pdf_Color_Rgb(0.93, 0.92, 0.92));
        $page->setLineWidth(0.5);
        $page->drawRectangle(25, $this->y, 570, $this->y - 25);
        $this->_setFontBold($page, 12);
        $page->setFillColor(new Zend_Pdf_Color_GrayScale(0));
        $page->drawText(__('Extra Fee Information'), 35, $this->y - 17, 'UTF-8');

        $this->y -= 25;

        $infoYTop = $this->y;

        $paymentExtraFee  = [];
        $shippingExtraFee = [];
        $extraFee         = [];
        foreach ($extraFeeTotal as $fee) {
            switch ((int) $fee['display_area']) {
                case DisplayArea::PAYMENT_METHOD:
                    $paymentExtraFee[] = $fee;
                    break;
                case DisplayArea::SHIPPING_METHOD:
                    $shippingExtraFee[] = $fee;
                    break;
                default:
                    $extraFee[] = $fee;
            }
        }

        $this->_setFontBold($page, 10);
        $page->drawText(__('Payment Extra Fee'), 35, $this->y - 17, 'UTF-8');
        $page->drawText(__('Shipping Extra Fee'), 290, $this->y - 17, 'UTF-8');
        $shipmentExtraFeeHeight = 34;
        $this->_setFontRegular($page, 9);

        $paymentExtraFeeHeight = 34;
        foreach ($paymentExtraFee as $item) {
            $page->drawText(
                $item['rule_label'] . ($item['label'] ? " - {$item['label']}" : ''),
                35,
                $this->y - $paymentExtraFeeHeight,
                'UTF-8'
            );
            $paymentExtraFeeHeight += 17;
        }

        foreach ($shippingExtraFee as $item) {
            $page->drawText(
                $item['rule_label'] . ($item['label'] ? " - {$item['label']}" : ''),
                290,
                $this->y - $shipmentExtraFeeHeight,
                'UTF-8'
            );
            $shipmentExtraFeeHeight += 17;
        }

        $this->y -= max($shipmentExtraFeeHeight, $paymentExtraFeeHeight);

        $this->_setFontBold($page, 10);
        $page->drawText(__('Extra Fee'), 35, $this->y - 5, 'UTF-8');

        $this->_setFontRegular($page, 9);

        $extraFeeHeight = 5 + 17;
        foreach ($extraFee as $item) {
            $page->drawText(
                $item['rule_label'] . ($item['label'] ? " - {$item['label']}" : ''),
                35,
                $this->y - $extraFeeHeight,
                'UTF-8'
            );
            $extraFeeHeight += 17;
        }

        $this->y -= $extraFeeHeight;

        $page->drawLine(25, $this->y, 25, $infoYTop);
        $page->drawLine(570, $this->y, 570, $infoYTop);
        $page->drawLine(25, $this->y, 570, $this->y);

        $this->y -= 15;
    }
}

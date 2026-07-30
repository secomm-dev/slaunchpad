<?php
/************************************************************
 * *
 *  * Copyright © Secomm. All rights reserved.
 *  * See COPYING.txt for license details.
 *  *
 *  * @author    Secomm Teams
 * *  @project   ZaloPay
 */

namespace Secomm\ZaloPay\Gateway\Request;

use Secomm\ZaloPay\Gateway\Helper\Rate;
use Magento\Framework\Escaper;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Payment\Gateway\ConfigInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Sales\Model\Order\Item;
use Magento\Sales\Model\Order\Payment;
use Magento\Store\Model\StoreManagerInterface;
use Secomm\ZaloPay\Helper\Data;

class ItemDetailsDataBuilder extends AbstractDataBuilder implements BuilderInterface
{
    /**
     * Item Id
     */
    const ITEM_ID = 'itemid';

    /**
     * Item Name
     */
    const ITEM_NAME = 'itemname';

    /**
     * Item Price
     */
    const ITEM_PRICE = 'itemprice';

    /**
     * Item Qty
     */
    const ITEM_QTY = 'itemquantity';

    /**
     * @var Json
     */
    private $serializer;
    /**
     * @var Rate
     */
    private $helperRate;

    /**
     * @var Escaper
     */
    private $escaper;

    /**
     * ItemDetailsDataBuilder constructor.
     * @param Rate $helperRate
     * @param Escaper $escaper
     * @param Json $serializer
     * @param Data $data
     */
    public function __construct(
        Rate           $helperRate,
        Escaper        $escaper,
        Json           $serializer,
        protected Data $data
    ) {
        $this->serializer = $serializer;
        $this->helperRate = $helperRate;
        $this->escaper = $escaper;
    }

    /**
     * @param array $buildSubject
     * @return array
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function build(array $buildSubject): array
    {
        $paymentDO = SubjectReader::readPayment($buildSubject);
        /** @var Payment $payment */
        $payment = $paymentDO->getPayment();
        $order = $payment->getOrder();
        $itemsData = [];

        /** @var Item $item */
        foreach ($order->getAllVisibleItems() as $item) {
            $itemsData[] = [
                self::ITEM_ID => $this->escaper->escapeHtml($item->getSku()),
                self::ITEM_NAME => $this->escaper->escapeHtml($this->data->removeSpecialChars($item->getName())),
                self::ITEM_PRICE => (float)$this->helperRate->getVndAmount($order, $item->getPrice()),
                self::ITEM_QTY => $item->getQtyOrdered()
            ];
        }

        return [
            self::ITEM => $this->serializer->serialize($itemsData)
        ];
    }
}

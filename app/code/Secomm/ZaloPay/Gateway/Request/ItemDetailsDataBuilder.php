<?php
/************************************************************
 * *
 *  * Copyright © Secomm. All rights reserved.
 *  * See COPYING.txt for license details.
 *  *
 *  * @author    Secomm Teams
 * *  @project   ZaloPay
 */
declare(strict_types=1);

namespace Secomm\ZaloPay\Gateway\Request;

use Secomm\ZaloPay\Gateway\Helper\Rate;
use Magento\Framework\Escaper;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Quote\Model\Quote as QuoteModel;
use Magento\Quote\Model\Quote\Item as QuoteItem;
use Magento\Sales\Model\Order\Item;
use Magento\Sales\Model\Order\Payment;
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
     * Build subject key carrying the QUOTE in the payment-first flow.
     */
    const QUOTE = 'quote';

    /**
     * ItemDetailsDataBuilder constructor.
     * @param Rate $helperRate
     * @param Escaper $escaper
     * @param Json $serializer
     * @param Data $data
     */
    public function __construct(
        private readonly Rate    $helperRate,
        private readonly Escaper $escaper,
        private readonly Json    $serializer,
        protected Data           $data
    ) {
    }

    /**
     * Two item sources:
     *  - legacy order-first: the sales order of the payment Data Object;
     *  - payment-first: the QUOTE passed in the build subject (line items
     *    priced in the quote display currency, qty via getQty()).
     *
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
        $quote = $buildSubject[self::QUOTE] ?? null;
        $itemsData = [];

        if ($quote instanceof QuoteModel) {
            $currency = (string)$quote->getQuoteCurrencyCode();
            /** @var QuoteItem $item */
            foreach ($quote->getAllVisibleItems() as $item) {
                $itemsData[] = [
                    self::ITEM_ID => $this->escaper->escapeHtml($item->getSku()),
                    self::ITEM_NAME => $this->escaper->escapeHtml($this->data->removeSpecialChars($item->getName())),
                    self::ITEM_PRICE => (float)$this->helperRate->getVndAmountByCurrency(
                        $currency,
                        (float)$item->getPrice()
                    ),
                    self::ITEM_QTY => (float)$item->getQty()
                ];
            }
        } else {
            $order = $payment->getOrder();
            /** @var Item $item */
            foreach ($order->getAllVisibleItems() as $item) {
                $itemsData[] = [
                    self::ITEM_ID => $this->escaper->escapeHtml($item->getSku()),
                    self::ITEM_NAME => $this->escaper->escapeHtml($this->data->removeSpecialChars($item->getName())),
                    self::ITEM_PRICE => (float)$this->helperRate->getVndAmount($order, $item->getPrice()),
                    self::ITEM_QTY => $item->getQtyOrdered()
                ];
            }
        }

        return [
            self::ITEM => $this->serializer->serialize($itemsData)
        ];
    }
}

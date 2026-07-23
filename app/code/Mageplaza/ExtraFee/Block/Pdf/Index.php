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

namespace Mageplaza\ExtraFee\Block\Pdf;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Sales\Api\CreditmemoRepositoryInterface;
use Magento\Sales\Api\Data\CreditmemoInterface;
use Magento\Sales\Api\Data\InvoiceInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\InvoiceRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Mageplaza\ExtraFee\Helper\Data;

/**
 * Class Index
 * @package Mageplaza\ExtraFee\Block\Pdf
 */
class Index extends Template
{
    /**
     * @var Data
     */
    protected $helper;

    /**
     * @var OrderRepositoryInterface
     */
    protected $orderRepository;

    /**
     * @var InvoiceRepositoryInterface
     */
    protected $invoiceRepository;

    /**
     * @var CreditmemoRepositoryInterface
     */
    protected $creditmemoRepository;

    /**
     * Index constructor.
     *
     * @param Context $context
     * @param Data $helper
     * @param OrderRepositoryInterface $orderRepository
     * @param InvoiceRepositoryInterface $invoiceRepository
     * @param CreditmemoRepositoryInterface $creditmemoRepository
     * @param array $data
     */
    public function __construct(
        Context $context,
        Data $helper,
        OrderRepositoryInterface $orderRepository,
        InvoiceRepositoryInterface $invoiceRepository,
        CreditmemoRepositoryInterface $creditmemoRepository,
        array $data = []
    ) {
        $this->helper               = $helper;
        $this->orderRepository      = $orderRepository;
        $this->invoiceRepository    = $invoiceRepository;
        $this->creditmemoRepository = $creditmemoRepository;

        parent::__construct($context, $data);
    }

    /**
     * @param string $area
     *
     * @return array
     */
    public function getExtraFee($area)
    {
        $result = [];
        $order  = $this->getOrder();
        $item   = $this->getItem();

        if (!($order && $item)) {
            return [];
        }

        $extraFee = $this->helper->getExtraFeeTotals($order);
        switch ($item->getEntityType()) {
            case 'shipment':
            case 'order':
                foreach ($extraFee as $fee) {
                    if ((int) $fee['display_area'] === $area) {
                        $result[] = $fee;
                    }
                }
                break;
            case 'invoice':
                if ($item->getId() === $this->helper->isInvoiced($order)) {
                    foreach ($extraFee as $fee) {
                        if ((int) $fee['display_area'] === $area) {
                            $result[] = $fee;
                        }
                    }
                }
                break;
            case 'creditmemo':
                if ($item->getId() === $this->helper->isRefunded($order)) {
                    foreach ($extraFee as $fee) {
                        if ((int) $fee['display_area'] === $area && $fee['rf'] !== '1') {
                            $result[] = $fee;
                        }
                    }
                }
                break;
        }

        return $result;
    }

    /**
     * @return OrderInterface
     */
    public function getOrder()
    {
        $orderId = $this->getData('order_id');

        return $this->orderRepository->get($orderId);
    }

    /**
     * @return CreditmemoInterface|InvoiceInterface|OrderInterface
     */
    public function getItem()
    {
        if ($this->hasData('invoice_id')) {
            $item = $this->invoiceRepository->get($this->getData('invoice_id'));
        } elseif ($this->hasData('creditmemo_id')) {
            $item = $this->creditmemoRepository->get($this->getData('creditmemo_id'));
        } else {
            $item = $this->orderRepository->get($this->getData('order_id'));
        }

        return $item;
    }
}

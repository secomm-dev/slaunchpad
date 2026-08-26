<?php
/**
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\PaymentCore\Block;

use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Sales\Api\Data\OrderInterface;
use Secomm\PaymentCore\Model\Lifecycle\CanContinuePayment;

/**
 * FEAT-CSWYEJ — Continue Payment button on the customer order view (spec §4.8).
 *
 * Runtime fix 2026-08-25: the order comes from the current_order registry (the
 * sales_order_view controller registers it); Template has no getOrder() of its
 * own. The guard re-validates server-side in the controller — hiding the button
 * is never the only protection (AC-006).
 */
class ContinuePaymentButton extends Template
{
    /**
     * @var CanContinuePayment
     */
    private readonly CanContinuePayment $canContinuePayment;

    /**
     * @var Registry
     */
    private readonly Registry $registry;

    /**
     * @param Context $context
     * @param CanContinuePayment $canContinuePayment
     * @param Registry $registry
     * @param array $data
     */
    public function __construct(
        Context $context,
        CanContinuePayment $canContinuePayment,
        Registry $registry,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->canContinuePayment = $canContinuePayment;
        $this->registry = $registry;
    }

    /**
     * Current order from the sales_order_view controller.
     */
    public function getOrder(): ?OrderInterface
    {
        $order = $this->registry->registry('current_order');
        return $order instanceof OrderInterface ? $order : null;
    }

    /**
     * Hide the block unless the guard passes.
     */
    protected function _toHtml(): string
    {
        $order = $this->getOrder();
        if ($order === null || !$this->canContinuePayment->canContinue($order)) {
            return '';
        }
        return parent::_toHtml();
    }

    /**
     * POST target for the Continue Payment form.
     */
    public function getActionUrl(): string
    {
        return $this->getUrl('paymentcore/payment/retry');
    }
}

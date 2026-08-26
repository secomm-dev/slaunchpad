<?php
/**
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\PaymentCore\Model\Adapter;

use Magento\Sales\Api\Data\OrderInterface;

/**
 * FEAT-CSWYEJ — provider-neutral contract (spec §4.3, DEC D1).
 *
 * The core owns the lifecycle; a provider module implements this interface and
 * registers its adapter in ITS OWN di.xml (AdapterPool adapters map). The core
 * must never reference a provider class or config path.
 */
interface PaymentProviderAdapterInterface
{
    /**
     * Payment method code this adapter serves (e.g. "vnpay").
     */
    public function getMethodCode(): string;

    /**
     * Build a FRESH, signed checkout URL for the still-payable order.
     * Providers that cannot regenerate return null (core denies Continue Payment).
     *
     * Note (DEC-FEATCSWYEJ-004): payment-status verification before cancel was
     * removed — the order state is the payment truth. Providers that later need
     * a verify API can add it to their own adapter as an extension point; the
     * core lifecycle no longer calls it.
     */
    public function getCheckoutUrl(OrderInterface $order): ?string;
}

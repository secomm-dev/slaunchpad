<?php
/**
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\PaymentCore\Model\Provider;

use Magento\Sales\Api\Data\OrderInterface;
use Secomm\PaymentCore\Model\Adapter\PaymentProviderAdapterInterface;

/**
 * FEAT-CSWYEJ / DEC-FEATCSWYEJ-002 + DEC-FEATCSWYEJ-004 — VNPAY adapter living
 * INSIDE Payment Core. The Vnpayment_VNPAY module stays pristine (third-party);
 * this adapter reads the extension's own config paths (strings, no class
 * imports) and speaks the VNPAY wire protocol directly.
 *
 * getCheckoutUrl: fresh signed URL, TxnRef = increment id (DEC D6 — sandbox
 * verified: multiple sessions per TxnRef are fine; the 15-minute token is
 * VNPAY-owned and never reused by us).
 *
 * DEC-FEATCSWYEJ-004 removed the pre-cancel provider verification (querydr):
 * expired + still pending = not paid. The adapter is now URL-generation only.
 */
class VnpayAdapter implements PaymentProviderAdapterInterface
{
    private const METHOD_CODE = 'vnpay';

    public function __construct(
        private readonly VnpayCheckoutUrl $checkoutUrl
    ) {
    }

    /**
     * @inheritdoc
     */
    public function getMethodCode(): string
    {
        return self::METHOD_CODE;
    }

    /**
     * @inheritdoc
     */
    public function getCheckoutUrl(OrderInterface $order): ?string
    {
        return $this->checkoutUrl->build($order);
    }
}

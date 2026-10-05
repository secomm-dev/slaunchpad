<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Launchpad\MageplazaExtraFeeFix\Plugin\EInvoice;

use Launchpad\MageplazaExtraFeeFix\Service\EInvoice\ExtraFeeInvoicePayloadProcessor;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order;
use Secomm\EInvoiceCore\Api\Data\IssueRequestInterface;
use Secomm\EInvoiceMisa\Model\Mapper\OrderToIssueRequest;
use Secomm\EInvoiceMisa\Model\Template\InvoiceTemplate;

/**
 * Promotes Mageplaza extra fees to named MeInvoice lines on the order payload.
 */
class OrderToIssueRequestPlugin
{
    public function __construct(
        private readonly ExtraFeeInvoicePayloadProcessor $payloadProcessor
    ) {
    }

    /**
     * @param OrderToIssueRequest $subject
     * @param IssueRequestInterface $result
     * @param OrderInterface $order
     * @param InvoiceTemplate $template
     * @param int $refRevision
     * @return IssueRequestInterface
     */
    public function afterMap(
        OrderToIssueRequest $subject,
        IssueRequestInterface $result,
        OrderInterface $order,
        InvoiceTemplate $template,
        int $refRevision = 0
    ): IssueRequestInterface {
        return $order instanceof Order
            ? $this->payloadProcessor->processForOrder($result, $order)
            : $result;
    }

    /**
     * mapReplacement() re-enters map() through the interceptor; the processor is
     * idempotent so the double interception is safe.
     *
     * @param OrderToIssueRequest $subject
     * @param IssueRequestInterface $result
     * @param OrderInterface $order
     * @param InvoiceTemplate $template
     * @param array<string, string> $origin
     * @param int $refRevision
     * @return IssueRequestInterface
     */
    public function afterMapReplacement(
        OrderToIssueRequest $subject,
        IssueRequestInterface $result,
        OrderInterface $order,
        InvoiceTemplate $template,
        array $origin,
        int $refRevision = 0
    ): IssueRequestInterface {
        return $order instanceof Order
            ? $this->payloadProcessor->processForOrder($result, $order)
            : $result;
    }
}

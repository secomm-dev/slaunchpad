<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Launchpad\MageplazaExtraFeeFix\Plugin\EInvoice;

use Launchpad\MageplazaExtraFeeFix\Service\EInvoice\ExtraFeeInvoicePayloadProcessor;
use Magento\Sales\Api\Data\CreditmemoInterface;
use Magento\Sales\Model\Order\Creditmemo;
use Secomm\EInvoiceCore\Api\Data\IssueRequestInterface;
use Secomm\EInvoiceMisa\Model\Mapper\CreditmemoToIssueRequest;
use Secomm\EInvoiceMisa\Model\Template\InvoiceTemplate;

/**
 * Promotes refunded Mageplaza extra fees to named MeInvoice lines on the
 * adjustment (credit memo) payload.
 */
class CreditmemoToIssueRequestPlugin
{
    public function __construct(
        private readonly ExtraFeeInvoicePayloadProcessor $payloadProcessor
    ) {
    }

    /**
     * @param CreditmemoToIssueRequest $subject
     * @param IssueRequestInterface $result
     * @param CreditmemoInterface $creditmemo
     * @param InvoiceTemplate $template
     * @param array<string, string> $origin
     * @return IssueRequestInterface
     */
    public function afterMap(
        CreditmemoToIssueRequest $subject,
        IssueRequestInterface $result,
        CreditmemoInterface $creditmemo,
        InvoiceTemplate $template,
        array $origin
    ): IssueRequestInterface {
        return $creditmemo instanceof Creditmemo
            ? $this->payloadProcessor->processForCreditmemo($result, $creditmemo)
            : $result;
    }
}

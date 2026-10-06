<?php

declare(strict_types=1);

namespace Secomm\EInvoiceMisa\Model\Mapper;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\CreditmemoRepositoryInterface;
use Secomm\EInvoiceCore\Api\AdjustmentRequestBuilderInterface;
use Secomm\EInvoiceCore\Api\Data\IssueRequestInterface;
use Secomm\EInvoiceMisa\Model\Template\InvoiceTemplateResolver;

/**
 * MISA adjustment request builder loading a credit memo and mapping to an adjustment payload.
 */
class CreditmemoIssueRequestBuilder implements AdjustmentRequestBuilderInterface
{
    /**
     * @param CreditmemoRepositoryInterface $creditmemoRepository
     * @param CreditmemoToIssueRequest $mapper
     * @param InvoiceTemplateResolver $templateResolver
     */
    public function __construct(
        private readonly CreditmemoRepositoryInterface $creditmemoRepository,
        private readonly CreditmemoToIssueRequest $mapper,
        private readonly InvoiceTemplateResolver $templateResolver
    ) {
    }

    /**
     * @inheritdoc
     */
    public function build(int $creditmemoId, array $context = []): IssueRequestInterface
    {
        try {
            $creditmemo = $this->creditmemoRepository->get($creditmemoId);
        } catch (NoSuchEntityException $exception) {
            throw new LocalizedException(__('Credit memo %1 not found.', $creditmemoId), $exception);
        }

        $storeId = (int) $creditmemo->getStoreId();
        $templateId = (string) ($context['template_id'] ?? '');
        $template = $templateId !== ''
            ? $this->templateResolver->resolveById($templateId, $storeId)
            : $this->templateResolver->resolveForStore($storeId);

        $origin = is_array($context['origin'] ?? null) ? $context['origin'] : [];

        return $this->mapper->map($creditmemo, $template, $origin);
    }
}

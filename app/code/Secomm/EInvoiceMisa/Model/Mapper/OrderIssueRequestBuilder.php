<?php

declare(strict_types=1);

namespace Secomm\EInvoiceMisa\Model\Mapper;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\OrderRepositoryInterface;
use Secomm\EInvoiceCore\Api\Data\IssueRequestInterface;
use Secomm\EInvoiceCore\Api\IssueRequestBuilderInterface;
use Secomm\EInvoiceLog\Model\IssueLog;
use Secomm\EInvoiceMisa\Model\Template\InvoiceTemplateResolver;

/**
 * MISA issue request builder loading order and mapping to provider payload.
 */
class OrderIssueRequestBuilder implements IssueRequestBuilderInterface
{
    /**
     * @param OrderRepositoryInterface $orderRepository
     * @param OrderToIssueRequest $orderMapper
     * @param InvoiceTemplateResolver $templateResolver
     */
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly OrderToIssueRequest $orderMapper,
        private readonly InvoiceTemplateResolver $templateResolver
    ) {
    }

    /**
     * @inheritdoc
     */
    public function build(int $orderId, array $context = []): IssueRequestInterface
    {
        try {
            $order = $this->orderRepository->get($orderId);
        } catch (NoSuchEntityException $exception) {
            throw new LocalizedException(__('Order %1 not found.', $orderId), $exception);
        }

        $storeId = (int) $order->getStoreId();
        $templateId = (string) ($context['template_id'] ?? '');
        $template = $templateId !== ''
            ? $this->templateResolver->resolveById($templateId, $storeId)
            : $this->templateResolver->resolveForStore($storeId);
        $refRevision = (int) ($context['ref_revision'] ?? 0);
        $referenceType = (int) ($context['reference_type'] ?? IssueLog::REFERENCE_TYPE_ORIGINAL);
        $origin = is_array($context['origin'] ?? null) ? $context['origin'] : [];

        if ($referenceType === IssueLog::REFERENCE_TYPE_REPLACE) {
            return $this->orderMapper->mapReplacement($order, $template, $origin, $refRevision);
        }

        return $this->orderMapper->map($order, $template, $refRevision);
    }
}

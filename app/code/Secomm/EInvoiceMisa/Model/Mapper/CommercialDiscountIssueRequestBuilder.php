<?php

declare(strict_types=1);

namespace Secomm\EInvoiceMisa\Model\Mapper;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\OrderRepositoryInterface;
use Secomm\EInvoiceCore\Api\CommercialDiscountRequestBuilderInterface;
use Secomm\EInvoiceCore\Api\Data\IssueRequestInterface;
use Secomm\EInvoiceMisa\Model\Template\InvoiceTemplateResolver;

/**
 * MISA commercial discount request builder.
 */
class CommercialDiscountIssueRequestBuilder implements CommercialDiscountRequestBuilderInterface
{
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly CommercialDiscountToIssueRequest $mapper,
        private readonly InvoiceTemplateResolver $templateResolver
    ) {
    }

    public function build(int $orderId, array $discount, array $context = []): IssueRequestInterface
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
        $origin = is_array($context['origin'] ?? null) ? $context['origin'] : [];
        $refRevision = (int) ($context['ref_revision'] ?? 0);

        return $this->mapper->map($order, $template, $origin, $discount, $refRevision);
    }
}

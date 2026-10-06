<?php

declare(strict_types=1);

namespace Secomm\EInvoiceMisa\Model\Template;

use Magento\Framework\Exception\LocalizedException;
use Secomm\EInvoiceMisa\Model\Config\MisaConfig;

/**
 * Resolves the invoice template to use when issuing, from store config or an explicit id.
 */
class InvoiceTemplateResolver
{
    /**
     * @param InvoiceTemplateListProvider $listProvider
     * @param MisaConfig $config
     */
    public function __construct(
        private readonly InvoiceTemplateListProvider $listProvider,
        private readonly MisaConfig $config
    ) {
    }

    /**
     * Resolve the template configured as the store default.
     *
     * @param int|null $storeId
     * @return InvoiceTemplate
     * @throws LocalizedException
     */
    public function resolveForStore(?int $storeId = null): InvoiceTemplate
    {
        $templateId = $this->config->getInvoiceTemplateId($storeId);
        if ($templateId === '') {
            throw new LocalizedException(
                __('No default invoice template is configured. Set it under Stores > Configuration > MISA API.')
            );
        }

        return $this->resolveById($templateId, $storeId);
    }

    /**
     * Resolve a specific template by its IPTemplateID, validating it exists and is active.
     *
     * @param string $ipTemplateId
     * @param int|null $storeId
     * @return InvoiceTemplate
     * @throws LocalizedException
     */
    public function resolveById(string $ipTemplateId, ?int $storeId = null): InvoiceTemplate
    {
        if ($ipTemplateId === '') {
            throw new LocalizedException(__('Please select an invoice template.'));
        }

        $template = $this->listProvider->getList($storeId)->getById($ipTemplateId);
        if ($template === null) {
            throw new LocalizedException(
                __('Invoice template "%1" was not found in MeInvoice. Refresh the template list.', $ipTemplateId)
            );
        }

        if ($template->isInactive()) {
            throw new LocalizedException(
                __('Invoice template "%1" is inactive in MeInvoice.', $template->getLabel())
            );
        }

        return $template;
    }
}

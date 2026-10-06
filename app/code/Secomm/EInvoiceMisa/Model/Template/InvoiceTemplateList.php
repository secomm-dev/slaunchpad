<?php

declare(strict_types=1);

namespace Secomm\EInvoiceMisa\Model\Template;

/**
 * Collection wrapper for invoice templates fetched from MeInvoice.
 */
class InvoiceTemplateList
{
    /**
     * @param InvoiceTemplate[] $items
     */
    public function __construct(
        private readonly array $items = []
    ) {
    }

    /**
     * All templates.
     *
     * @return InvoiceTemplate[]
     */
    public function getItems(): array
    {
        return $this->items;
    }

    /**
     * Only active templates that have both series and template id.
     *
     * @return InvoiceTemplate[]
     */
    public function getActive(): array
    {
        return array_values(array_filter(
            $this->items,
            static fn (InvoiceTemplate $template): bool =>
                !$template->isInactive()
                && $template->getIpTemplateId() !== ''
                && $template->getInvSeries() !== ''
        ));
    }

    /**
     * Find a template by its IPTemplateID.
     *
     * @param string $ipTemplateId
     * @return InvoiceTemplate|null
     */
    public function getById(string $ipTemplateId): ?InvoiceTemplate
    {
        foreach ($this->items as $template) {
            if ($template->getIpTemplateId() === $ipTemplateId) {
                return $template;
            }
        }

        return null;
    }

    /**
     * Whether the list has no templates.
     *
     * @return bool
     */
    public function isEmpty(): bool
    {
        return $this->items === [];
    }
}

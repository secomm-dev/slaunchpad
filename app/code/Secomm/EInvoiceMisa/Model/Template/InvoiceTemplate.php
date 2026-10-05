<?php

declare(strict_types=1);

namespace Secomm\EInvoiceMisa\Model\Template;

/**
 * Immutable representation of a MeInvoice invoice template (mau hoa don).
 */
class InvoiceTemplate
{
    /**
     * @param string $ipTemplateId
     * @param string $invSeries
     * @param string $templateName
     * @param bool $inactive
     * @param bool $isSendSummary
     */
    public function __construct(
        private readonly string $ipTemplateId,
        private readonly string $invSeries,
        private readonly string $templateName = '',
        private readonly bool $inactive = false,
        private readonly bool $isSendSummary = false
    ) {
    }

    /**
     * MeInvoice template identifier (IPTemplateID).
     *
     * @return string
     */
    public function getIpTemplateId(): string
    {
        return $this->ipTemplateId;
    }

    /**
     * Invoice series (InvSeries).
     *
     * @return string
     */
    public function getInvSeries(): string
    {
        return $this->invSeries;
    }

    /**
     * Human-readable template name.
     *
     * @return string
     */
    public function getTemplateName(): string
    {
        return $this->templateName;
    }

    /**
     * Whether the template is inactive on MeInvoice side.
     *
     * @return bool
     */
    public function isInactive(): bool
    {
        return $this->inactive;
    }

    /**
     * Whether template requires summary invoice flag (IsSendSummary from API §4).
     */
    public function isSendSummary(): bool
    {
        return $this->isSendSummary;
    }

    /**
     * Label combining series and name for admin display.
     *
     * @return string
     */
    public function getLabel(): string
    {
        $name = $this->templateName !== '' ? $this->templateName : $this->ipTemplateId;

        return trim(sprintf('%s - %s', $this->invSeries, $name), ' -');
    }

    /**
     * Build a template from a raw MeInvoice API row.
     *
     * @param array<string, mixed> $row
     * @return self
     */
    public static function fromApiRow(array $row): self
    {
        $name = (string) (
            $row['InvTemplateName']
            ?? $row['TemplateName']
            ?? $row['InvoiceTemplateName']
            ?? $row['Description']
            ?? ''
        );

        return new self(
            (string) ($row['IPTemplateID'] ?? ''),
            (string) ($row['InvSeries'] ?? ''),
            $name,
            (bool) ($row['Inactive'] ?? false),
            (bool) ($row['IsSendSummary'] ?? $row['isSendSummary'] ?? false)
        );
    }

    /**
     * Export to array for grid/data provider consumption.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'ip_template_id' => $this->ipTemplateId,
            'inv_series' => $this->invSeries,
            'template_name' => $this->templateName,
            'inactive' => $this->inactive,
            'is_send_summary' => $this->isSendSummary,
        ];
    }
}

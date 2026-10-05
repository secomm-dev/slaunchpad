<?php

declare(strict_types=1);

namespace Secomm\EInvoiceMisa\Model\Template;

use Secomm\EInvoiceCore\Api\TemplateOptionsProviderInterface;
use Secomm\EInvoiceMisa\Model\Config\MisaConfig;

/**
 * Exposes MeInvoice templates as admin selection options.
 */
class MisaTemplateOptionsProvider implements TemplateOptionsProviderInterface
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
     * @inheritdoc
     */
    public function getOptions(?int $storeId = null): array
    {
        $options = [];
        foreach ($this->listProvider->getList($storeId)->getActive() as $template) {
            $options[] = [
                'value' => $template->getIpTemplateId(),
                'label' => $template->getLabel(),
            ];
        }

        return $options;
    }

    /**
     * @inheritdoc
     */
    public function getDefaultId(?int $storeId = null): string
    {
        return $this->config->getInvoiceTemplateId($storeId);
    }
}

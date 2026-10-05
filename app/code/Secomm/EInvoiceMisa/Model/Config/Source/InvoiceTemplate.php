<?php

declare(strict_types=1);

namespace Secomm\EInvoiceMisa\Model\Config\Source;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Data\OptionSourceInterface;
use Magento\Store\Model\StoreManagerInterface;
use Secomm\EInvoiceMisa\Model\Template\InvoiceTemplateListProvider;

/**
 * Admin config source listing MeInvoice templates for the current scope.
 */
class InvoiceTemplate implements OptionSourceInterface
{
    /**
     * @param InvoiceTemplateListProvider $listProvider
     * @param RequestInterface $request
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        private readonly InvoiceTemplateListProvider $listProvider,
        private readonly RequestInterface $request,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * @inheritdoc
     *
     * @return array<int, array<string, string>>
     */
    public function toOptionArray(): array
    {
        $options = [['value' => '', 'label' => (string) __('-- Please select a template --')]];

        foreach ($this->listProvider->getList($this->resolveStoreId())->getActive() as $template) {
            $options[] = [
                'value' => $template->getIpTemplateId(),
                'label' => $template->getLabel(),
            ];
        }

        return $options;
    }

    /**
     * Resolve the store scope currently edited in admin config.
     *
     * @return int|null
     */
    private function resolveStoreId(): ?int
    {
        $storeParam = $this->request->getParam('store');
        if ($storeParam !== null && $storeParam !== '') {
            return (int) $storeParam;
        }

        $websiteParam = $this->request->getParam('website');
        if ($websiteParam !== null && $websiteParam !== '') {
            try {
                return (int) $this->storeManager->getWebsite($websiteParam)->getDefaultStore()->getId();
            } catch (\Throwable $exception) {
                return null;
            }
        }

        return null;
    }
}

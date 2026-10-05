<?php

declare(strict_types=1);

namespace Secomm\EInvoiceMisa\Model\Config\Source;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Data\OptionSourceInterface;
use Magento\Store\Model\StoreManagerInterface;
use Secomm\EInvoiceMisa\Model\Certificate\CertificateListProvider;

/**
 * Admin config source listing HSM certificates for SignType=2 publish.
 */
class CertificateSn implements OptionSourceInterface
{
    public function __construct(
        private readonly CertificateListProvider $listProvider,
        private readonly RequestInterface $request,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function toOptionArray(): array
    {
        $options = [
            [
                'value' => '',
                'label' => (string) __('Default (MeInvoice auto-selects when only one certificate)'),
            ],
        ];

        foreach ($this->listProvider->getList($this->resolveStoreId()) as $certificate) {
            $options[] = [
                'value' => $certificate->getCertificateSn(),
                'label' => $certificate->getLabel(),
            ];
        }

        return $options;
    }

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
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }
}

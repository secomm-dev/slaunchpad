<?php
declare(strict_types=1);

namespace Secomm\AiDiscoverability\ViewModel;

use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\StoreManagerInterface;
use Secomm\AiDiscoverability\Model\Config;

/**
 * Provides the store-scoped /llms.txt URL for the describedby link tag.
 */
class DescribedBy implements ArgumentInterface
{
    /**
     * @param StoreManagerInterface $storeManager current store resolver
     * @param Config $config module config reader
     */
    public function __construct(
        private readonly StoreManagerInterface $storeManager,
        private readonly Config $config
    ) {
    }

    /**
     * Whether the link tag should be rendered for the current store view.
     *
     * @return bool true when the feature is enabled
     */
    public function isEnabled(): bool
    {
        return $this->config->isEnabled((int) $this->storeManager->getStore()->getId());
    }

    /**
     * Absolute URL of the llms.txt document for the current store view.
     *
     * @return string llms.txt URL
     */
    public function getUrl(): string
    {
        $store = $this->storeManager->getStore();

        return rtrim((string) $store->getBaseUrl(), '/') . '/llms.txt';
    }
}

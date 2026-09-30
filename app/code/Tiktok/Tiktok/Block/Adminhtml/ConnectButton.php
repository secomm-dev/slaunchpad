<?php
declare(strict_types=1);

namespace Tiktok\Tiktok\Block\Adminhtml;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Url\Helper\Data;
use Tiktok\Tiktok\Model\ScopeManagerBuilder;
use Tiktok\Tiktok\Model\Storage\Admin;

/**
 * TikTok Connect Button Block
 */
class ConnectButton extends Template
{
    /**
     * Constructor
     *
     * @param \Magento\Backend\Block\Template\Context $context
     * @param \Tiktok\Tiktok\Model\Storage\Admin $adminStorage
     * @param \Tiktok\Tiktok\Model\ScopeManagerBuilder $scopeManagerBuilder
     * @param \Magento\Framework\Url\Helper\Data $urlHelper
     * @param array $data
     */
    public function __construct(
        Context $context,
        private readonly Admin $adminStorage,
        private readonly ScopeManagerBuilder $scopeManagerBuilder,
        private readonly Data $urlHelper,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * @inheritdoc
     */
    public function toHtml()
    {
        $isLinked = $this->adminStorage->get('is_linked_to_website');
        if ($isLinked) {
            return '';
        }

        return parent::toHtml();
    }

    /**
     * Get the auth URL
     *
     * @return string
     */
    public function getAuthUrl(): string
    {
        $websiteId = $this->adminStorage->get('current_website_id');
        if (!$websiteId) {
            return '';
        }

        $scope = $this->scopeManagerBuilder->create($websiteId);
        $authUrl = $scope->getAuthUrl();
        if (!$authUrl) {
            return '';
        }

        $externalData = $this->adminStorage->get('external_data');
        if (!$externalData) {
            return '';
        }

        return $this->urlHelper->addRequestParam($authUrl, ['external_data' => $externalData]);
    }
}

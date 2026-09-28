<?php
declare(strict_types=1);

namespace Tiktok\Tiktok\Block\Adminhtml;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Url\Helper\Data;
use Tiktok\Tiktok\Model\ScopeManagerBuilder;
use Tiktok\Tiktok\Model\Storage\Admin;

/**
 * TikTok Splash Page Block
 */
class SplashPage extends Template
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
        if (!$isLinked) {
            return '';
        }

        return parent::toHtml();
    }

    /**
     * Get the iframe URL
     *
     * @return string
     */
    public function getIframeUrl(): string
    {
        $websiteId = $this->adminStorage->get('current_website_id');
        if (!$websiteId) {
            return '';
        }

        $scope = $this->scopeManagerBuilder->create($websiteId);
        $manageUrl = $scope->getManageUrl();
        if (!$manageUrl) {
            return '';
        }

        $externalData = $this->adminStorage->get('encoded_external_data');
        if (!$externalData) {
            return '';
        }

        return $this->urlHelper->addRequestParam($manageUrl, ['external_data' => $externalData]);
    }
}

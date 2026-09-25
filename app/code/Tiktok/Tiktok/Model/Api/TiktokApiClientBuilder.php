<?php
declare(strict_types=1);

namespace Tiktok\Tiktok\Model\Api;

use Tiktok\Tiktok\Model\ScopeManagerBuilder;

/**
 * Manages the creation of TiktokApiClient instances based on the website ID.
 */
class TiktokApiClientBuilder
{
    /**
     * @var \Tiktok\Tiktok\Model\Api\TiktokApiClientFactory
     */
    private TiktokApiClientFactory $tiktokApiClientFactory;

    /**
     * @var \Tiktok\Tiktok\Model\ScopeManagerBuilder
     */
    private ScopeManagerBuilder $scopeManagerBuilder;

    /**
     * Init dependencies
     *
     * @param \Tiktok\Tiktok\Model\ScopeManagerBuilder $scopeManagerBuilder
     * @param \Tiktok\Tiktok\Model\Api\TiktokApiClientFactory $tiktokApiClientFactory
     */
    public function __construct(
        ScopeManagerBuilder $scopeManagerBuilder,
        TiktokApiClientFactory $tiktokApiClientFactory
    ) {
        $this->tiktokApiClientFactory = $tiktokApiClientFactory;
        $this->scopeManagerBuilder = $scopeManagerBuilder;
    }

    /**
     * Create TikTok API Client
     *
     * @param int $websiteId
     * @return \Tiktok\Tiktok\Model\Api\TiktokApiClient
     */
    public function create(int $websiteId): TiktokApiClient
    {
        $scopeManager = $this->scopeManagerBuilder->create($websiteId);

        return $this->tiktokApiClientFactory->create([
            'scopeManager' => $scopeManager]);
    }
}

<?php
declare(strict_types=1);

namespace Tiktok\Tiktok\Model;

use Tiktok\Tiktok\Model\Config\ScopeManager;
use Tiktok\Tiktok\Model\Config\ScopeManagerFactory;

class ScopeManagerBuilder
{
    /**
     * @var \Tiktok\Tiktok\Model\Config\ScopeManagerFactory
     */
    private ScopeManagerFactory $scopeManagerFactory;

    /**
     * Init dependencies
     *
     * @param \Tiktok\Tiktok\Model\Config\ScopeManagerFactory $scopeManagerFactory
     */
    public function __construct(ScopeManagerFactory $scopeManagerFactory)
    {
        $this->scopeManagerFactory = $scopeManagerFactory;
    }

    /**
     * Create scope manager
     *
     * @param int $websiteId
     *
     * @return \Tiktok\Tiktok\Model\Config\ScopeManager
     */
    public function create(int $websiteId): ScopeManager
    {
        return $this->scopeManagerFactory->create(['websiteId' => $websiteId]);
    }
}

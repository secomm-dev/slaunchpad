<?php
declare(strict_types=1);

namespace Secomm\AiCommerce\Model\StoreContext;

use Secomm\AiCommerce\Model\Config;

/**
 * Strict base-path/store boundary guard (BUG-D4QK1Q §9).
 *
 * The configured endpoint base path is part of a Store View's public API
 * contract: a request path is valid for a target store ONLY when the path
 * prefix equals that store's own configured base path. One shared matcher
 * serves both the router (admission) and the controllers (defense-in-depth)
 * so the two layers can never disagree about the boundary.
 */
class PathGuard
{
    /**
     * @param Config $config module config reader (store-scoped base path)
     */
    public function __construct(private readonly Config $config)
    {
    }

    /**
     * Whether the request path starts at the target store's base path.
     *
     * Boundary-exact: base path "ai" matches "ai" and "ai/..." but never
     * "ai-extra/..." — and a store configured to "agent" never admits "/ai".
     *
     * @param string $pathInfo raw request path info
     * @param int $storeId resolved target store view id
     * @return bool true when the path agrees with the store's configuration
     */
    public function matches(string $pathInfo, int $storeId): bool
    {
        $path = trim($pathInfo, '/');

        if ($path === '') {
            return false;
        }

        $basePath = $this->config->getEndpointPath($storeId);

        return $path === $basePath || str_starts_with($path, $basePath . '/');
    }
}

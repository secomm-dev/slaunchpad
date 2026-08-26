<?php
declare(strict_types=1);

namespace Secomm\AiCommerce\Controller;

use Magento\Framework\App\ActionFactory;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Router\ActionList;
use Magento\Framework\App\RouterInterface;
use Secomm\AiCommerce\Model\Config;

/**
 * Matches the configurable /<base-path>/* public read surface BEFORE the
 * standard router.
 *
 * The base path is resolved from configuration (seocomm_ai_commerce/general/
 * endpoint_path, store-view scoped, default "ai") — never hardcoded. This
 * router is the single routing authority: the module declares NO standard
 * frontName route, so changing the base path cannot leave the old /ai/* URLs
 * live behind the standard router.
 *
 * Only the four approved routes exist; nothing else under the base path is
 * matched (falls through to Magento no-route → deterministic 404). GET/HEAD
 * match their actions; every other verb is routed to the 405 envelope so no
 * mutation can ever execute commerce logic here. Query strings beyond the
 * fixed bound are rejected with the 400 envelope.
 */
class Router implements RouterInterface
{
    private const MODULE = 'Secomm_AiCommerce';
    private const MAX_QUERY_STRING = 512;

    /**
     * @param ActionFactory $actionFactory action factory
     * @param ActionList $actionList router action list
     * @param Config $config module config reader (endpoint base path)
     */
    public function __construct(
        private readonly ActionFactory $actionFactory,
        private readonly ActionList $actionList,
        private readonly Config $config
    ) {
    }

    /**
     * Match a /<base-path>/* request to its fixed action.
     *
     * @param RequestInterface $request incoming request
     * @return ActionInterface|null matched action or null (no-route)
     */
    public function match(RequestInterface $request): ?ActionInterface
    {
        $path = trim($request->getPathInfo(), '/');

        if ($path === '') {
            return null;
        }

        $basePath = $this->config->getEndpointPath();

        if ($path !== $basePath && !str_starts_with($path, $basePath . '/')) {
            return null;
        }

        $queryString = (string) $request->getServer('QUERY_STRING', '');

        if (strlen($queryString) > self::MAX_QUERY_STRING) {
            return $this->actionFactory->create(BadRequest::class);
        }

        if ($request instanceof HttpRequest && !in_array($request->getMethod(), ['GET', 'HEAD'], true)) {
            return $this->actionFactory->create(MethodNotAllowed::class);
        }

        $tail = trim(substr($path, strlen($basePath)), '/');

        switch ($tail) {
            case 'store':
                return $this->action('store', 'view');
            case 'catalog/search':
                return $this->action('catalog', 'search');
            case 'categories':
                return $this->action('categories', 'index');
            default:
                // products/{sku} or unmatched.
                if (str_starts_with($tail, 'products/') && substr_count($tail, '/') === 1) {
                    $sku = substr($tail, strlen('products/'));
                    $request->setParam('sku', $sku);

                    return $this->action('products', 'view');
                }

                return null;
        }
    }

    /**
     * Create a module action.
     *
     * @param string $controller controller name (lowercase, underscore-free)
     * @param string $action action name
     * @return ActionInterface|null action or null when unresolvable
     */
    private function action(string $controller, string $action): ?ActionInterface
    {
        $actionClassName = $this->actionList->get(self::MODULE, null, $controller, $action);

        if ($actionClassName === null) {
            return null;
        }

        return $this->actionFactory->create($actionClassName);
    }
}

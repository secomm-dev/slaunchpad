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
use Secomm\AiCommerce\Model\StoreContext\PathGuard;
use Secomm\AiCommerce\Model\StoreContext\Resolver;
use Secomm\AiCommerce\Service\InvalidStoreException;

/**
 * Matches the configurable /<base-path>/* public read surface BEFORE the
 * standard router, with strict store-scoped admission (BUG-D4QK1Q):
 *
 *     target store resolution (data only, no global mutation)
 *         ↓
 *     store-scoped enabled + endpoint-path config
 *         ↓
 *     strict base-path validation
 *         ↓
 *     controller routing
 *
 * The TARGET store is resolved first, from the same single V1 mechanism the
 * controllers use (`store` query parameter, absent → installation default,
 * via StoreContext\Resolver::resolveAsData()). All config checks then use
 * that explicit store id — never ambient/default-scope config — so a Store
 * View override (e.g. endpoint_path "agent") both routes its own path and
 * rejects the default path. The base path is part of the store's public API
 * contract: wrong path + valid ?store is a 404, never admitted.
 *
 * Only the four approved routes exist; nothing else under the base path is
 * matched (no-route → deterministic 404). GET/HEAD match their actions; every
 * other verb is routed to the 405 envelope. Query strings beyond the fixed
 * bound are rejected with the 400 envelope.
 */
class Router implements RouterInterface
{
    private const MODULE = 'Secomm_AiCommerce';
    private const MAX_QUERY_STRING = 512;

    /**
     * @param ActionFactory $actionFactory action factory
     * @param ActionList $actionList router action list
     * @param Config $config module config reader (store-scoped)
     * @param Resolver $storeResolver shared store-context resolution contract
     * @param PathGuard $pathGuard strict base-path/store boundary matcher
     */
    public function __construct(
        private readonly ActionFactory $actionFactory,
        private readonly ActionList $actionList,
        private readonly Config $config,
        private readonly Resolver $storeResolver,
        private readonly PathGuard $pathGuard
    ) {
    }

    /**
     * Match a /<base-path>/* request for its resolved target store.
     *
     * @param RequestInterface $request incoming request
     * @return ActionInterface|null matched action or null (no-route)
     */
    public function match(RequestInterface $request): ?ActionInterface
    {
        $pathInfo = (string) $request->getPathInfo();

        // Target store FIRST — as data, no current-store mutation.
        try {
            $store = $this->storeResolver->resolveAsData(
                $request instanceof HttpRequest ? $request->getParam(Resolver::PARAM_STORE) : null
            );
        } catch (InvalidStoreException $exception) {
            // Invalid store code: fall back to the installation default for
            // path admission only, so a default-path request still reaches
            // the controller and keeps the documented 400 invalid_store
            // envelope; any other path is a 404.
            try {
                $store = $this->storeResolver->resolveAsData(null);
            } catch (InvalidStoreException $unresolvable) {
                return null;
            }
        }

        $storeId = (int) $store->getId();

        // Store-scoped enabled check on the TARGET store (BUG-D4QK1Q §10).
        if (!$this->config->isEnabled($storeId)) {
            return null;
        }

        // Strict store-scoped base-path admission (BUG-D4QK1Q §9).
        if (!$this->pathGuard->matches($pathInfo, $storeId)) {
            return null;
        }

        $queryString = (string) $request->getServer('QUERY_STRING', '');

        if (strlen($queryString) > self::MAX_QUERY_STRING) {
            return $this->actionFactory->create(BadRequest::class);
        }

        if ($request instanceof HttpRequest && !in_array($request->getMethod(), ['GET', 'HEAD'], true)) {
            return $this->actionFactory->create(MethodNotAllowed::class);
        }

        $tail = trim(substr(trim($pathInfo, '/'), strlen($this->config->getEndpointPath($storeId))), '/');

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

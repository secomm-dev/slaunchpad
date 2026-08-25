<?php
declare(strict_types=1);

namespace Secomm\AiCommerce\Controller;

use Magento\Framework\App\ActionFactory;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Route\ConfigInterface;
use Magento\Framework\App\Router\ActionList;
use Magento\Framework\App\RouterInterface;

/**
 * Matches the fixed /ai/* public surface BEFORE the standard router.
 *
 * Only the four approved routes exist; nothing else under /ai/ is matched
 * (falls through to Magento no-route → deterministic 404). GET/HEAD match
 * their actions; every other verb is routed to the 405 envelope so no
 * mutation can ever execute commerce logic here. Query strings beyond the
 * fixed bound are rejected with the 400 envelope.
 */
class Router implements RouterInterface
{
    private const PREFIX = 'ai';
    private const MAX_QUERY_STRING = 512;

    /**
     * @param ActionFactory $actionFactory action factory
     * @param ActionList $actionList router action list
     * @param ConfigInterface $routeConfig route config
     */
    public function __construct(
        private readonly ActionFactory $actionFactory,
        private readonly ActionList $actionList,
        private readonly ConfigInterface $routeConfig
    ) {
    }

    /**
     * Match an /ai/* request to its fixed action.
     *
     * @param RequestInterface $request incoming request
     * @return ActionInterface|null matched action or null (no-route)
     */
    public function match(RequestInterface $request): ?ActionInterface
    {
        $path = trim($request->getPathInfo(), '/');

        if ($path === '' || strtok($path, '/') !== self::PREFIX) {
            return null;
        }

        $modules = $this->routeConfig->getModulesByFrontName(self::PREFIX);

        if (empty($modules)) {
            return null;
        }

        $queryString = (string) $request->getServer('QUERY_STRING', '');

        if (strlen($queryString) > self::MAX_QUERY_STRING) {
            return $this->actionFactory->create(BadRequest::class);
        }

        if ($request instanceof HttpRequest && !in_array($request->getMethod(), ['GET', 'HEAD'], true)) {
            return $this->actionFactory->create(MethodNotAllowed::class);
        }

        $tail = trim((string) substr($path, strlen(self::PREFIX)), '/');

        switch ($tail) {
            case 'store':
                return $this->action($modules[0], 'store', 'view');
            case 'catalog/search':
                return $this->action($modules[0], 'catalog', 'search');
            case 'categories':
                return $this->action($modules[0], 'categories', 'index');
            default:
                // /ai/products/{sku} or unmatched.
                if (str_starts_with($tail, 'products/') && substr_count($tail, '/') === 1) {
                    $sku = substr($tail, strlen('products/'));
                    $request->setParam('sku', $sku);

                    return $this->action($modules[0], 'products', 'view');
                }

                return null;
        }
    }

    /**
     * Create a module action.
     *
     * @param string $module module name
     * @param string $controller controller name (lowercase, underscore-free)
     * @param string $action action name
     * @return ActionInterface|null action or null when unresolvable
     */
    private function action(string $module, string $controller, string $action): ?ActionInterface
    {
        $actionClassName = $this->actionList->get($module, null, $controller, $action);

        if ($actionClassName === null) {
            return null;
        }

        return $this->actionFactory->create($actionClassName);
    }
}

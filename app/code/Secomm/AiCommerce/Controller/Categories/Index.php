<?php
declare(strict_types=1);

namespace Secomm\AiCommerce\Controller\Categories;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Controller\ResultInterface;
use Secomm\AiCommerce\Model\Cache\ResponseCache;
use Secomm\AiCommerce\Model\Config;
use Secomm\AiCommerce\Model\StoreContext\PathGuard;
use Secomm\AiCommerce\Model\StoreContext\Resolver as StoreResolver;
use Secomm\AiCommerce\Service\Catalog\CategoryTreeService;
use Secomm\AiCommerce\Service\FacadeException;
use Secomm\AiCommerce\Service\NotFoundException;
use Secomm\AiCommerce\Service\Response\Responder;

/**
 * GET/HEAD /ai/categories — single-load store category tree (thin controller).
 */
class Index implements HttpGetActionInterface
{
    private const ROUTE = 'categories';

    /**
     * @param StoreResolver $storeResolver public store context resolver
     * @param PathGuard $pathGuard strict base-path/store boundary guard
     * @param Config $config module config reader
     * @param ResponseCache $responseCache internal response cache
     * @param CategoryTreeService $categoryTreeService single-load tree service
     * @param Responder $responder shared JSON HTTP responder
     * @param HttpRequest $request HTTP request (store param)
     */
    public function __construct(
        private readonly StoreResolver $storeResolver,
        private readonly PathGuard $pathGuard,
        private readonly Config $config,
        private readonly ResponseCache $responseCache,
        private readonly CategoryTreeService $categoryTreeService,
        private readonly Responder $responder,
        private readonly HttpRequest $request
    ) {
    }

    /**
     * Serve the store category tree DTO.
     *
     * @return ResultInterface raw JSON result or error envelope
     */
    public function execute(): ResultInterface
    {
        try {
            $store = $this->storeResolver->resolve($this->request->getParam('store'));
            $storeId = (int) $store->getId();
            $lifetime = $this->config->getCacheLifetime($storeId);

            // Defense-in-depth (BUG-D4QK1Q §11): the resolved target store is
            // only servable through ITS configured base path.
            if (!$this->pathGuard->matches((string) $this->request->getPathInfo(), (int) $store->getId())) {
                throw new NotFoundException(__('Resource not found.'));
            }

            if (!$this->config->isEnabled($storeId)) {
                throw new NotFoundException(__('Resource not found.'));
            }

            $params = [];
            $cached = $this->responseCache->load($storeId, self::ROUTE, $params);

            if ($cached !== null) {
                return $this->responder->json($cached, $lifetime);
            }

            $data = ['categories' => $this->categoryTreeService->getTree($store)];
            $this->responseCache->save($data, $storeId, self::ROUTE, $params, $lifetime);

            return $this->responder->json($data, $lifetime);
        } catch (FacadeException $exception) {
            return $this->responder->error($exception);
        } catch (\Throwable $exception) {
            return $this->responder->internalError($exception);
        }
    }
}

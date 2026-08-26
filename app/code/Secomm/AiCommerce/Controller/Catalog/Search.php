<?php
declare(strict_types=1);

namespace Secomm\AiCommerce\Controller\Catalog;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Controller\ResultInterface;
use Secomm\AiCommerce\Model\Cache\ResponseCache;
use Secomm\AiCommerce\Model\Config;
use Secomm\AiCommerce\Model\StoreContext\PathGuard;
use Secomm\AiCommerce\Model\StoreContext\Resolver as StoreResolver;
use Secomm\AiCommerce\Service\Catalog\SearchService;
use Secomm\AiCommerce\Service\FacadeException;
use Secomm\AiCommerce\Service\NotFoundException;
use Secomm\AiCommerce\Service\Response\Responder;

/**
 * GET/HEAD /ai/catalog/search — bounded storefront search (thin controller).
 */
class Search implements HttpGetActionInterface
{
    private const ROUTE = 'search';

    /**
     * @param StoreResolver $storeResolver public store context resolver
     * @param PathGuard $pathGuard strict base-path/store boundary guard
     * @param Config $config module config reader
     * @param ResponseCache $responseCache internal response cache
     * @param SearchService $searchService delegating search service
     * @param Responder $responder shared JSON HTTP responder
     * @param HttpRequest $request HTTP request (params)
     */
    public function __construct(
        private readonly StoreResolver $storeResolver,
        private readonly PathGuard $pathGuard,
        private readonly Config $config,
        private readonly ResponseCache $responseCache,
        private readonly SearchService $searchService,
        private readonly Responder $responder,
        private readonly HttpRequest $request
    ) {
    }

    /**
     * Serve the parsed, bounded search result DTO.
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

            $params = $this->request->getParams();
            $cached = $this->responseCache->load($storeId, self::ROUTE, $params);

            if ($cached !== null) {
                return $this->responder->json($cached, $lifetime);
            }

            $data = $this->searchService->search($store, $params);
            $this->responseCache->save($data, $storeId, self::ROUTE, $params, $lifetime);

            return $this->responder->json($data, $lifetime);
        } catch (FacadeException $exception) {
            return $this->responder->error($exception);
        } catch (\Throwable $exception) {
            return $this->responder->internalError($exception);
        }
    }
}

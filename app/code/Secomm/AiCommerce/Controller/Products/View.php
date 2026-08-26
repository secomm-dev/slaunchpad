<?php
declare(strict_types=1);

namespace Secomm\AiCommerce\Controller\Products;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Controller\ResultInterface;
use Secomm\AiCommerce\Model\Cache\ResponseCache;
use Secomm\AiCommerce\Model\Config;
use Secomm\AiCommerce\Model\StoreContext\Resolver as StoreResolver;
use Secomm\AiCommerce\Service\Catalog\ProductFetcher;
use Secomm\AiCommerce\Service\FacadeException;
use Secomm\AiCommerce\Service\NotFoundException;
use Secomm\AiCommerce\Service\Response\Responder;

/**
 * GET/HEAD /ai/products/{sku} — public product detail (thin controller).
 *
 * Cached through the shared per-store ResponseCache under the SAME contract
 * as the other read endpoints (SPEC-TASK-AIC-PDC1): warm hits skip the
 * ProductRepository/MSI/pricing pipeline entirely; cold responses carry
 * public Cache-Control + ETag/304 semantics from the shared Responder.
 * Errors (disabled store, malformed SKU, non-public product) are thrown
 * before any cache write and stay no-store.
 */
class View implements HttpGetActionInterface
{
    private const ROUTE = 'product';

    /**
     * @param StoreResolver $storeResolver public store context resolver
     * @param Config $config module config reader
     * @param ResponseCache $responseCache internal response cache
     * @param ProductFetcher $productFetcher public product lookup + DTO
     * @param Responder $responder shared JSON HTTP responder
     * @param HttpRequest $request HTTP request (sku param set by router)
     */
    public function __construct(
        private readonly StoreResolver $storeResolver,
        private readonly Config $config,
        private readonly ResponseCache $responseCache,
        private readonly ProductFetcher $productFetcher,
        private readonly Responder $responder,
        private readonly HttpRequest $request
    ) {
    }

    /**
     * Serve the public product detail DTO (cached when the store lifetime > 0).
     *
     * @return ResultInterface raw JSON result or error envelope
     */
    public function execute(): ResultInterface
    {
        try {
            $store = $this->storeResolver->resolve($this->request->getParam('store'));
            $storeId = (int) $store->getId();

            if (!$this->config->isEnabled($storeId)) {
                throw new NotFoundException(__('Resource not found.'));
            }

            $sku = trim((string) $this->request->getParam('sku', ''));
            $params = ['sku' => $sku];
            $lifetime = $this->config->getCacheLifetime($storeId);
            $cached = $this->responseCache->load($storeId, self::ROUTE, $params);

            if ($cached !== null) {
                return $this->responder->json($cached, $lifetime);
            }

            $data = $this->productFetcher->fetch($sku, $store);
            $this->responseCache->save($data, $storeId, self::ROUTE, $params, $lifetime);

            return $this->responder->json($data, $lifetime);
        } catch (FacadeException $exception) {
            return $this->responder->error($exception);
        } catch (\Throwable $exception) {
            return $this->responder->internalError($exception);
        }
    }
}

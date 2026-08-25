<?php
declare(strict_types=1);

namespace Secomm\AiCommerce\Controller\Products;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Controller\ResultInterface;
use Secomm\AiCommerce\Model\Config;
use Secomm\AiCommerce\Model\StoreContext\Resolver as StoreResolver;
use Secomm\AiCommerce\Service\Catalog\ProductFetcher;
use Secomm\AiCommerce\Service\FacadeException;
use Secomm\AiCommerce\Service\NotFoundException;
use Secomm\AiCommerce\Service\Response\Responder;

/**
 * GET/HEAD /ai/products/{sku} — public product detail (thin controller).
 * Product responses are NOT internally cached: prices/salability can move
 * with every save and product volume makes targeted invalidation the only
 * correct policy — correctness over maximum caching.
 */
class View implements HttpGetActionInterface
{
    /**
     * @param StoreResolver $storeResolver public store context resolver
     * @param Config $config module config reader
     * @param ProductFetcher $productFetcher public product lookup + DTO
     * @param Responder $responder shared JSON HTTP responder
     * @param HttpRequest $request HTTP request (sku param set by router)
     */
    public function __construct(
        private readonly StoreResolver $storeResolver,
        private readonly Config $config,
        private readonly ProductFetcher $productFetcher,
        private readonly Responder $responder,
        private readonly HttpRequest $request
    ) {
    }

    /**
     * Serve the public product detail DTO.
     *
     * @return ResultInterface raw JSON result or error envelope
     */
    public function execute(): ResultInterface
    {
        try {
            $store = $this->storeResolver->resolve($this->request->getParam('store'));

            if (!$this->config->isEnabled((int) $store->getId())) {
                throw new NotFoundException(__('Resource not found.'));
            }

            $data = $this->productFetcher->fetch((string) $this->request->getParam('sku', ''), $store);

            return $this->responder->json($data, 0);
        } catch (FacadeException $exception) {
            return $this->responder->error($exception);
        } catch (\Throwable $exception) {
            return $this->responder->internalError($exception);
        }
    }
}

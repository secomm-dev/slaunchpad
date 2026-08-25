<?php
declare(strict_types=1);

namespace Secomm\AiCommerce\Controller\Store;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Controller\ResultInterface;
use Secomm\AiCommerce\Model\Cache\ResponseCache;
use Secomm\AiCommerce\Model\Config;
use Secomm\AiCommerce\Model\StoreContext\Resolver as StoreResolver;
use Secomm\AiCommerce\Service\FacadeException;
use Secomm\AiCommerce\Service\NotFoundException;
use Secomm\AiCommerce\Service\Response\Responder;
use Secomm\AiCommerce\Service\Response\StoreDto;

/**
 * GET/HEAD /ai/store — store context metadata (thin controller).
 */
class View implements HttpGetActionInterface
{
    private const ROUTE = 'store';

    /**
     * @param StoreResolver $storeResolver public store context resolver
     * @param Config $config module config reader
     * @param ResponseCache $responseCache internal response cache
     * @param StoreDto $storeDto store DTO builder
     * @param Responder $responder shared JSON HTTP responder
     * @param HttpRequest $request HTTP request (store param)
     */
    public function __construct(
        private readonly StoreResolver $storeResolver,
        private readonly Config $config,
        private readonly ResponseCache $responseCache,
        private readonly StoreDto $storeDto,
        private readonly Responder $responder,
        private readonly HttpRequest $request
    ) {
    }

    /**
     * Serve the store context DTO.
     *
     * @return ResultInterface raw JSON result or error envelope
     */
    public function execute(): ResultInterface
    {
        try {
            $store = $this->storeResolver->resolve($this->request->getParam('store'));
            $lifetime = $this->config->getCacheLifetime((int) $store->getId());

            if (!$this->config->isEnabled((int) $store->getId())) {
                throw new NotFoundException(__('Resource not found.'));
            }

            $params = [];
            $cached = $this->responseCache->load((int) $store->getId(), self::ROUTE, $params);

            if ($cached !== null) {
                return $this->responder->json($cached, $lifetime);
            }

            $data = $this->storeDto->toArray($store);
            $this->responseCache->save($data, (int) $store->getId(), self::ROUTE, $params, $lifetime);

            return $this->responder->json($data, $lifetime);
        } catch (FacadeException $exception) {
            return $this->responder->error($exception);
        } catch (\Throwable $exception) {
            return $this->responder->internalError($exception);
        }
    }
}

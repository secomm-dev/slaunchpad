<?php
declare(strict_types=1);

namespace Secomm\AiDiscoverability\Controller\Index;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Api\StoreRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;
use Secomm\AiDiscoverability\Model\Config;
use Secomm\AiDiscoverability\Service\LlmsTxtProvider;

/**
 * Serves GET/HEAD /llms.txt. Session-less, read-only, cache-friendly.
 * Disabled feature yields an explicit 404 (SPEC-TASK-0X552E §6).
 *
 * Store selection (BUG-D4QK1Q §13/§14): the same `?store=<code>` selector
 * the AI read endpoints use resolves the TARGET store view BEFORE any
 * generation/config lookup, so a store with its own `endpoint_path` (e.g.
 * "agent") gets discovery output advertising ITS effective URLs. Absent or
 * unrecognized selector keeps the current-store (default) behavior.
 */
class Index implements HttpGetActionInterface
{
    private const CONTENT_TYPE = 'text/plain; charset=UTF-8';
    private const CACHE_CONTROL_NO_STORE = 'no-store, no-cache, must-revalidate';
    private const PARAM_STORE = 'store';

    /**
     * @param Config $config module config reader
     * @param LlmsTxtProvider $provider cached llms.txt body provider
     * @param StoreManagerInterface $storeManager current store resolver
     * @param StoreRepositoryInterface $storeRepository store registry (query-param resolution)
     * @param ResultFactory $resultFactory raw result factory
     * @param HttpResponse $response HTTP response (status header for 304/404)
     * @param HttpRequest $request HTTP request (method, store param, If-None-Match)
     */
    public function __construct(
        private readonly Config $config,
        private readonly LlmsTxtProvider $provider,
        private readonly StoreManagerInterface $storeManager,
        private readonly StoreRepositoryInterface $storeRepository,
        private readonly ResultFactory $resultFactory,
        private readonly HttpResponse $response,
        private readonly HttpRequest $request
    ) {
    }

    /**
     * Resolve the TARGET store view id for this request.
     *
     * @return int resolved target store view id (current store when the
     *              selector is absent or not an active store code)
     */
    private function resolveTargetStoreId(): int
    {
        $storeCode = trim((string) $this->request->getParam(self::PARAM_STORE));

        if ($storeCode !== '') {
            try {
                return (int) $this->storeRepository->getActiveStoreByCode($storeCode)->getId();
            } catch (NoSuchEntityException $exception) {
                // Unknown/inactive selector: fall through to current store.
            }
        }

        return (int) $this->storeManager->getStore()->getId();
    }

    /**
     * Serve the llms.txt plain-text body for the resolved target store view.
     *
     * HTTP cache semantics derive from the same effective store-scoped cache
     * lifetime used by the provider: lifetime > 0 advertises
     * `public, max-age=<lifetime>` with ETag/conditional support; lifetime 0
     * (merchant disabled caching) advertises no-store and skips ETag/304 so no
     * intermediary may serve a stale body.
     *
     * @return ResultInterface raw text result (404 result when disabled)
     */
    public function execute(): ResultInterface
    {
        $storeId = $this->resolveTargetStoreId();

        if (!$this->config->isEnabled($storeId)) {
            return $this->notFound();
        }

        $body = $this->provider->get($storeId);
        $lifetime = $this->config->getCacheLifetime($storeId);

        $result = $this->rawResult();
        $result->setHeader('Content-Type', self::CONTENT_TYPE);

        if ($lifetime > 0) {
            $this->setCacheableHeaders($result, $body, $lifetime);
        } else {
            $result->setHeader('Cache-Control', self::CACHE_CONTROL_NO_STORE);
        }

        if ($this->request->getMethod() !== 'HEAD') {
            $result->setContents($body);
        } else {
            $result->setContents('');
        }

        return $result;
    }

    /**
     * Set cache headers and answer If-None-Match with 304 when unchanged.
     *
     * @param Raw $result raw result to decorate
     * @param string $body generated llms.txt body
     * @param int $lifetime effective store-scoped cache lifetime in seconds
     * @return void
     */
    private function setCacheableHeaders(Raw $result, string $body, int $lifetime): void
    {
        $etag = '"' . sha1($body) . '"';
        $result->setHeader('Cache-Control', 'public, max-age=' . $lifetime);
        $result->setHeader('ETag', $etag);

        if (trim((string) $this->request->getHeader('If-None-Match')) === $etag) {
            $this->response->setStatusHeader(304);
        }
    }

    /**
     * Create a raw result instance.
     *
     * @return Raw raw result
     */
    private function rawResult(): Raw
    {
        /** @var Raw $result */
        $result = $this->resultFactory->create(ResultFactory::TYPE_RAW);

        return $result;
    }

    /**
     * Build the 404 result used when the feature is disabled for the store.
     *
     * @return Raw 404 raw result
     */
    private function notFound(): Raw
    {
        $this->response->setStatusHeader(404);
        $result = $this->rawResult();
        $result->setHeader('Content-Type', self::CONTENT_TYPE);

        return $result;
    }
}

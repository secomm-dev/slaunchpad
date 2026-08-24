<?php
declare(strict_types=1);

namespace Secomm\AiDiscoverability\Controller\Index;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Store\Model\StoreManagerInterface;
use Secomm\AiDiscoverability\Model\Config;
use Secomm\AiDiscoverability\Service\LlmsTxtProvider;

/**
 * Serves GET/HEAD /llms.txt. Session-less, read-only, cache-friendly.
 * Disabled feature yields an explicit 404 (SPEC-TASK-0X552E §6).
 */
class Index implements HttpGetActionInterface
{
    private const CONTENT_TYPE = 'text/plain; charset=UTF-8';
    private const CACHE_CONTROL_MAX_AGE = 3600;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var LlmsTxtProvider
     */
    private $provider;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var ResultFactory
     */
    private $resultFactory;

    /**
     * @var HttpResponse
     */
    private $response;

    /**
     * @var HttpRequest
     */
    private $request;

    /**
     * @param Config $config module config reader
     * @param LlmsTxtProvider $provider cached llms.txt body provider
     * @param StoreManagerInterface $storeManager current store resolver
     * @param ResultFactory $resultFactory raw result factory
     * @param HttpResponse $response HTTP response (status header for 304/404)
     * @param HttpRequest $request HTTP request (method, If-None-Match)
     */
    public function __construct(
        Config $config,
        LlmsTxtProvider $provider,
        StoreManagerInterface $storeManager,
        ResultFactory $resultFactory,
        HttpResponse $response,
        HttpRequest $request
    ) {
        $this->config = $config;
        $this->provider = $provider;
        $this->storeManager = $storeManager;
        $this->resultFactory = $resultFactory;
        $this->response = $response;
        $this->request = $request;
    }

    /**
     * Serve the llms.txt plain-text body for the current store view.
     *
     * @return ResultInterface raw text result (404 result when disabled)
     */
    public function execute(): ResultInterface
    {
        $storeId = (int) $this->storeManager->getStore()->getId();

        if (!$this->config->isEnabled($storeId)) {
            return $this->notFound();
        }

        $body = $this->provider->get($storeId);

        $result = $this->rawResult();
        $result->setHeader('Content-Type', self::CONTENT_TYPE);
        $this->setCommonHeaders($result, $body);

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
     * @return void
     */
    private function setCommonHeaders(Raw $result, string $body): void
    {
        $etag = '"' . sha1($body) . '"';
        $result->setHeader('Cache-Control', 'public, max-age=' . self::CACHE_CONTROL_MAX_AGE);
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

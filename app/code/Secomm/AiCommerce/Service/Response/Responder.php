<?php
declare(strict_types=1);

namespace Secomm\AiCommerce\Service\Response;

use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Psr\Log\LoggerInterface;
use Secomm\AiCommerce\Service\FacadeException;

/**
 * Shared JSON HTTP responder. Controllers delegate ALL HTTP semantics here
 * and stay thin: JSON encoding, content type, cache headers, ETag/304,
 * HEAD empty-body handling and the deterministic error envelope.
 */
class Responder
{
    private const CONTENT_TYPE = 'application/json; charset=UTF-8';
    private const CACHE_CONTROL_NO_STORE = 'no-store, no-cache, must-revalidate';

    /**
     * @param ResultFactory $resultFactory raw result factory
     * @param Json $json json serializer
     * @param HttpResponse $response HTTP response (status header for 304)
     * @param HttpRequest $request HTTP request (method, If-None-Match)
     * @param ErrorEnvelope $errorEnvelope error envelope builder
     * @param LoggerInterface $logger operation logger
     */
    public function __construct(
        private readonly ResultFactory $resultFactory,
        private readonly Json $json,
        private readonly HttpResponse $response,
        private readonly HttpRequest $request,
        private readonly ErrorEnvelope $errorEnvelope,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Emit a successful JSON DTO body with cache headers.
     *
     * @param mixed[] $data DTO array
     * @param int $cacheLifetime effective store-scoped cache lifetime (0 = no-store)
     * @return ResultInterface raw JSON result
     */
    public function json(array $data, int $cacheLifetime): ResultInterface
    {
        return $this->raw($this->json->serialize($data), 200, $cacheLifetime);
    }

    /**
     * Emit a deterministic error envelope for a caught facade exception.
     *
     * @param FacadeException $exception caught facade exception
     * @return ResultInterface raw JSON error result
     */
    public function error(FacadeException $exception): ResultInterface
    {
        $error = $this->errorEnvelope->build($exception);

        return $this->errorRaw($error['status'], $error['body']);
    }

    /**
     * Emit the 405 envelope for non-GET/HEAD methods.
     *
     * @return ResultInterface raw JSON error result
     */
    public function methodNotAllowed(): ResultInterface
    {
        $error = $this->errorEnvelope->methodNotAllowed();

        return $this->errorRaw($error['status'], $error['body']);
    }

    /**
     * Emit the generic 500 envelope for unexpected failures.
     *
     * @param \Throwable $exception unexpected exception (details only logged, never emitted)
     * @return ResultInterface raw JSON error result
     */
    public function internalError(\Throwable $exception): ResultInterface
    {
        $this->logger->error('Secomm_AiCommerce unexpected failure: ' . $exception->getMessage());
        $error = $this->errorEnvelope->internalError();

        return $this->errorRaw($error['status'], $error['body']);
    }

    /**
     * Emit a raw JSON body with cache/ETag/304 and HEAD semantics.
     *
     * @param string $body JSON body
     * @param int $status HTTP status
     * @param int $cacheLifetime cache lifetime in seconds (0 = no-store)
     * @return ResultInterface raw result
     */
    private function raw(string $body, int $status, int $cacheLifetime): ResultInterface
    {
        $result = $this->rawResult();
        $this->response->setStatusHeader($status);
        $result->setHeader('Content-Type', self::CONTENT_TYPE);

        if ($cacheLifetime > 0) {
            $etag = '"' . sha1($body) . '"';
            $result->setHeader('Cache-Control', 'public, max-age=' . $cacheLifetime);
            $result->setHeader('ETag', $etag);

            if (trim((string) $this->request->getHeader('If-None-Match')) === $etag) {
                $this->response->setStatusHeader(304);
            }
        } else {
            $result->setHeader('Cache-Control', self::CACHE_CONTROL_NO_STORE);
        }

        $result->setContents($this->request->getMethod() === 'HEAD' ? '' : $body);

        return $result;
    }

    /**
     * Emit an error envelope with no-store semantics.
     *
     * @param int $status HTTP status
     * @param mixed[] $body error envelope body
     * @return ResultInterface raw result
     */
    private function errorRaw(int $status, array $body): ResultInterface
    {
        $result = $this->rawResult();
        $this->response->setStatusHeader($status);
        $result->setHeader('Content-Type', self::CONTENT_TYPE);
        $result->setHeader('Cache-Control', self::CACHE_CONTROL_NO_STORE);
        $result->setContents($this->request->getMethod() === 'HEAD' ? '' : $this->json->serialize($body));

        return $result;
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
}

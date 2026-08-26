<?php
declare(strict_types=1);

namespace Secomm\AiCommerce\Controller;

use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\ResultInterface;
use Secomm\AiCommerce\Service\Response\Responder;

/**
 * Deterministic 405 envelope for non-GET/HEAD verbs on /ai/* routes.
 * No commerce logic executes for POST/PUT/PATCH/DELETE. CSRF-exempt on
 * purpose: the 405 is emitted BEFORE any processing and carries no state
 * change, and the core CSRF validator would otherwise hijack POSTs into a
 * storefront redirect instead of the contract-mandated envelope.
 */
class MethodNotAllowed implements ActionInterface, CsrfAwareActionInterface
{
    /**
     * @param Responder $responder shared JSON HTTP responder
     */
    public function __construct(private readonly Responder $responder)
    {
    }

    /**
     * Emit the 405 envelope.
     *
     * @return ResultInterface raw JSON error result
     */
    public function execute(): ResultInterface
    {
        $result = $this->responder->methodNotAllowed();
        $result->setHeader('Allow', 'GET, HEAD');

        return $result;
    }

    /**
     * Every request is valid from a CSRF standpoint: nothing mutates.
     *
     * @param RequestInterface $request incoming request
     * @return bool always true
     */
    public function validateForCsrf(RequestInterface $request): bool
    {
        return true;
    }

    /**
     * Never reached — validateForCsrf() always passes.
     *
     * @param RequestInterface $request incoming request
     * @return InvalidRequestException|null always null
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }
}

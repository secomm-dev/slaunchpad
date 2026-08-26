<?php
declare(strict_types=1);

namespace Secomm\AiCommerce\Controller;

use Magento\Framework\App\ActionInterface;
use Magento\Framework\Controller\ResultInterface;
use Secomm\AiCommerce\Service\InvalidParameterException;
use Secomm\AiCommerce\Service\Response\Responder;

/**
 * Deterministic 400 envelope for oversized query strings on /ai/* routes.
 */
class BadRequest implements ActionInterface
{
    /**
     * @param Responder $responder shared JSON HTTP responder
     */
    public function __construct(private readonly Responder $responder)
    {
    }

    /**
     * Emit the 400 envelope.
     *
     * @return ResultInterface raw JSON error result
     */
    public function execute(): ResultInterface
    {
        return $this->responder->error(new InvalidParameterException(__('Invalid request parameters.')));
    }
}

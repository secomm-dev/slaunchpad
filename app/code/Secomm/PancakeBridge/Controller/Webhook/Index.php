<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\PancakeBridge\Controller\Webhook;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\ResultFactory;
use Secomm\FulfillmentCore\Model\Inbound\InboundUpdateApplier;
use Secomm\FulfillmentCore\Model\Log\FulfillmentLogger;
use Secomm\PancakeBridge\Model\Order\PancakeOrderExporter;
use Secomm\PancakeBridge\Model\Config\PancakeConfig;
use Secomm\PancakeFunction\Model\Inbound\OrderPayloadParser;

/**
 * Optional inbound POST /pancake/webhook/index?secret=... POS OpenAPI does not document Magento webhooks.
 */
class Index extends Action implements CsrfAwareActionInterface, HttpPostActionInterface
{
    public function __construct(
        Context $context,
        private readonly PancakeConfig $config,
        private readonly OrderPayloadParser $parser,
        private readonly InboundUpdateApplier $applier,
        private readonly FulfillmentLogger $fulfillmentLogger
    ) {
        parent::__construct($context);
    }

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    public function validateForCsrf(RequestInterface $request): bool
    {
        return true;
    }

    public function execute()
    {
        try {
            return $this->handle();
        } catch (\Throwable $e) {
            $this->fulfillmentLogger->error(
                PancakeOrderExporter::SERVICE_CODE,
                'Pancake webhook failed.',
                ['error' => $e->getMessage()]
            );
            return $this->json(['ok' => false, 'error' => 'internal_error']);
        }
    }

    private function handle(): Json
    {
        $secret = $this->config->getWebhookSecret();
        if ($secret !== '' && !hash_equals($secret, (string) $this->getRequest()->getParam('secret', ''))) {
            $this->fulfillmentLogger->warning(
                PancakeOrderExporter::SERVICE_CODE,
                'Pancake webhook rejected: invalid secret.'
            );
            return $this->json(['ok' => false, 'error' => 'invalid_secret']);
        }

        $decoded = json_decode((string) $this->getRequest()->getContent(), true);
        if (!is_array($decoded)) {
            return $this->json(['ok' => false, 'error' => 'invalid_json']);
        }

        $order = isset($decoded['data']) && is_array($decoded['data']) ? $decoded['data'] : $decoded;
        $update = $this->parser->parse($order);
        if ($update === null) {
            return $this->json(['ok' => true, 'result' => 'ignored']);
        }

        $this->applier->apply($update);
        return $this->json(['ok' => true, 'result' => 'applied']);
    }

    /**
     * @param array<string, mixed> $body
     */
    private function json(array $body): Json
    {
        /** @var Json $result */
        $result = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        $result->setData($body);
        return $result;
    }
}

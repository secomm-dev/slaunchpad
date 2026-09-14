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
use Secomm\FulfillmentCore\Api\ExportPushStatus;
use Secomm\FulfillmentCore\Model\FulfillmentExport;
use Secomm\FulfillmentCore\Model\Inbound\InboundUpdateApplier;
use Secomm\FulfillmentCore\Model\Log\FulfillmentLogger;
use Secomm\FulfillmentCore\Model\ResourceModel\FulfillmentExport\CollectionFactory as ExportCollectionFactory;
use Secomm\Pancake\Model\Inbound\OrderPayloadParser;
use Secomm\Pancake\Model\ServiceCode;
use Secomm\PancakeBridge\Model\Config\PancakeConfig;

/**
 * Inbound POST from Pancake POS webhook (OpenAPI: PUT /shops/{SHOP_ID}, webhook_types=orders).
 * Body is WebhookOrderResponse (or wrapped in data/order). Poll cron remains the fallback path.
 *
 * URL: POST /pancake/webhook/index?secret=<pancake/webhook/secret>
 */
class Index extends Action implements CsrfAwareActionInterface, HttpPostActionInterface
{
    public function __construct(
        Context $context,
        private readonly PancakeConfig $config,
        private readonly OrderPayloadParser $parser,
        private readonly InboundUpdateApplier $applier,
        private readonly ExportCollectionFactory $exportCollectionFactory,
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
                ServiceCode::CODE,
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
                ServiceCode::CODE,
                'Pancake webhook rejected: invalid secret.'
            );
            return $this->json(['ok' => false, 'error' => 'invalid_secret']);
        }

        $decoded = json_decode((string) $this->getRequest()->getContent(), true);
        if (!is_array($decoded)) {
            return $this->json(['ok' => false, 'error' => 'invalid_json']);
        }

        $order = $this->unwrapOrder($decoded);
        if ($order === null) {
            $this->fulfillmentLogger->info(ServiceCode::CODE, 'Pancake webhook ignored: empty order payload.');
            return $this->json(['ok' => true, 'result' => 'ignored']);
        }

        $update = $this->parser->parse($order);
        if ($update === null) {
            $this->fulfillmentLogger->info(ServiceCode::CODE, 'Pancake webhook ignored: unparseable order.');
            return $this->json(['ok' => true, 'result' => 'ignored']);
        }

        $export = $this->resolveExport($order, $update->getExternalOrderId());
        if ($export === null) {
            $this->fulfillmentLogger->info(
                ServiceCode::CODE,
                'Pancake webhook ignored: no Magento-origin export.',
                ['external_order_id' => $update->getExternalOrderId()]
            );
            return $this->json(['ok' => true, 'result' => 'ignored']);
        }

        $result = $this->applier->applyToExport($export, $update);
        $this->fulfillmentLogger->info(
            ServiceCode::CODE,
            'Pancake webhook processed.',
            [
                'export_id' => (int) $export->getEntityId(),
                'result' => $result,
            ]
        );

        return $this->json(['ok' => true, 'result' => $result === 'error' ? 'ignored' : 'applied']);
    }

    /**
     * Unwrap POS webhook body shapes into a flat order array.
     *
     * @param array<string, mixed> $decoded Raw JSON body
     * @return array<string, mixed>|null Order object or null when missing
     */
    private function unwrapOrder(array $decoded): ?array
    {
        if (isset($decoded['data']) && is_array($decoded['data'])) {
            $decoded = $decoded['data'];
        }
        if (isset($decoded['order']) && is_array($decoded['order'])) {
            $decoded = $decoded['order'];
        }
        if ($decoded === []) {
            return null;
        }

        return $decoded;
    }

    /**
     * Resolve Magento-origin export by POS external id, then by custom_id/increment_id.
     *
     * @param array<string, mixed> $order Webhook order payload
     * @param string $externalOrderId Parsed external id from OrderPayloadParser
     */
    private function resolveExport(array $order, string $externalOrderId): ?FulfillmentExport
    {
        $export = $this->findExportByExternalId($externalOrderId);
        if ($export !== null) {
            return $export;
        }

        $customId = isset($order['custom_id']) ? trim((string) $order['custom_id']) : '';
        if ($customId === '') {
            return null;
        }

        return $this->findExportByIncrementId($customId);
    }

    private function findExportByExternalId(string $externalOrderId): ?FulfillmentExport
    {
        if ($externalOrderId === '') {
            return null;
        }
        $collection = $this->exportCollectionFactory->create();
        $collection->addFieldToFilter('service_code', ServiceCode::CODE);
        $collection->addFieldToFilter('external_order_id', $externalOrderId);
        $collection->addFieldToFilter('origin', ExportPushStatus::ORIGIN_MAGENTO);
        $collection->setPageSize(1);
        /** @var FulfillmentExport $item */
        $item = $collection->getFirstItem();

        return $item->getEntityId() ? $item : null;
    }

    private function findExportByIncrementId(string $incrementId): ?FulfillmentExport
    {
        $collection = $this->exportCollectionFactory->create();
        $collection->addFieldToFilter('service_code', ServiceCode::CODE);
        $collection->addFieldToFilter('increment_id', $incrementId);
        $collection->addFieldToFilter('origin', ExportPushStatus::ORIGIN_MAGENTO);
        $collection->setPageSize(1);
        /** @var FulfillmentExport $item */
        $item = $collection->getFirstItem();

        return $item->getEntityId() ? $item : null;
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

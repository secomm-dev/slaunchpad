<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Pancake\Model\Order;

use Magento\Sales\Api\Data\OrderInterface;
use Psr\Log\LoggerInterface;
use Secomm\FulfillmentCore\Api\Data\ExportResult;
use Secomm\FulfillmentCore\Api\Data\ExportResultInterface;
use Secomm\FulfillmentCore\Api\OrderExporterInterface;
use Secomm\FulfillmentCore\Api\OrderFulfillmentSourceResolverInterface;
use Secomm\FulfillmentCore\Api\WarehouseMapResolverInterface;
use Secomm\Pancake\Model\Client\PosClient;
use Secomm\Pancake\Model\Client\PosClientException;
use Secomm\Pancake\Model\Config\PancakeConfig;

class PancakeOrderExporter implements OrderExporterInterface
{
    public const SERVICE_CODE = 'pancake';
    public const ERROR_WAREHOUSE_UNMAPPED = 'warehouse_unmapped';

    public function __construct(
        private readonly PancakeConfig $config,
        private readonly PayloadBuilder $payloadBuilder,
        private readonly PosClient $posClient,
        private readonly OrderFulfillmentSourceResolverInterface $sourceResolver,
        private readonly WarehouseMapResolverInterface $warehouseMapResolver,
        private readonly LoggerInterface $logger
    ) {
    }

    public function getServiceCode(): string
    {
        return self::SERVICE_CODE;
    }

    public function isEnabled(?int $storeId = null): bool
    {
        return $this->config->isEnabled($storeId);
    }

    public function export(OrderInterface $order): ExportResultInterface
    {
        $storeId = $order->getStoreId() !== null ? (int) $order->getStoreId() : null;
        if (!$this->isEnabled($storeId)) {
            return ExportResult::fail('pancake_disabled');
        }

        $sources = $this->sourceResolver->resolveSourceCodes($order);
        $primary = $sources[0] ?? '';
        if ($primary === '') {
            $this->logger->error(
                'Pancake export blocked: no MSI source.',
                ['increment_id' => (string) $order->getIncrementId()]
            );
            return ExportResult::fail(self::ERROR_WAREHOUSE_UNMAPPED);
        }

        $map = $this->warehouseMapResolver->resolve(self::SERVICE_CODE, $primary);
        if ($map === null) {
            $this->logger->error(
                'Pancake export blocked: warehouse unmapped.',
                [
                    'increment_id' => (string) $order->getIncrementId(),
                    'source_code' => $primary,
                ]
            );
            return ExportResult::fail(self::ERROR_WAREHOUSE_UNMAPPED);
        }

        try {
            $payload = $this->payloadBuilder->build($order, $map->getExternalWarehouseId());
            $response = $this->posClient->createOrder($payload, $storeId);
            $externalId = $this->extractOrderId($response);
            if ($externalId === null) {
                $this->logger->error(
                    'Pancake create-order missing id.',
                    ['increment_id' => (string) $order->getIncrementId()]
                );
                return ExportResult::fail('pancake_missing_id');
            }

            $this->logger->info(
                'Pancake create-order succeeded.',
                [
                    'increment_id' => (string) $order->getIncrementId(),
                    'source_code' => $primary,
                ]
            );

            return ExportResult::ok($externalId);
        } catch (PosClientException $e) {
            $this->logger->error(
                'Pancake create-order failed.',
                ['increment_id' => (string) $order->getIncrementId(), 'error' => $e->getMessage()]
            );
            return ExportResult::fail($e->getMessage());
        }
    }

    /**
     * @param array<string, mixed> $response
     */
    private function extractOrderId(array $response): ?string
    {
        foreach (['id', 'order_id'] as $key) {
            if (isset($response[$key]) && $response[$key] !== '') {
                return (string) $response[$key];
            }
        }
        $data = $response['data'] ?? null;
        if (is_array($data) && isset($data['id']) && $data['id'] !== '') {
            return (string) $data['id'];
        }

        return null;
    }
}

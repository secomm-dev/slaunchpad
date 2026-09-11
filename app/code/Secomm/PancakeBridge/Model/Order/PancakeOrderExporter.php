<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\PancakeBridge\Model\Order;

use Magento\Sales\Api\Data\OrderInterface;
use Secomm\FulfillmentCore\Model\Log\FulfillmentLogger;
use Secomm\FulfillmentCore\Api\Data\ExportResult;
use Secomm\FulfillmentCore\Api\Data\ExportResultInterface;
use Secomm\FulfillmentCore\Api\OrderExporterInterface;
use Secomm\FulfillmentCore\Api\OrderFulfillmentSourceResolverInterface;
use Secomm\FulfillmentCore\Api\WarehouseMapResolverInterface;
use Secomm\Pancake\Model\Client\PosClient;
use Secomm\Pancake\Model\Client\PosClientException;
use Secomm\Pancake\Model\Order\PayloadBuilder;
use Secomm\PancakeBridge\Model\Config\PancakeConfig;
use Secomm\Pancake\Model\ServiceCode;

class PancakeOrderExporter implements OrderExporterInterface
{
    public const SERVICE_CODE = ServiceCode::CODE;
    public const ERROR_WAREHOUSE_UNMAPPED = 'warehouse_unmapped';

    public function __construct(
        private readonly PancakeConfig $config,
        private readonly PayloadBuilder $payloadBuilder,
        private readonly PosClient $posClient,
        private readonly OrderFulfillmentSourceResolverInterface $sourceResolver,
        private readonly WarehouseMapResolverInterface $warehouseMapResolver,
        private readonly FulfillmentLogger $fulfillmentLogger
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
            $this->fulfillmentLogger->error(
                self::SERVICE_CODE,
                'Pancake export blocked: no MSI source.',
                ['increment_id' => (string) $order->getIncrementId()]
            );
            return ExportResult::fail(self::ERROR_WAREHOUSE_UNMAPPED);
        }

        $map = $this->warehouseMapResolver->resolve(self::SERVICE_CODE, $primary);
        if ($map === null) {
            $this->fulfillmentLogger->error(
                self::SERVICE_CODE,
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
                $this->fulfillmentLogger->error(
                    self::SERVICE_CODE,
                    'Pancake create-order missing id.',
                    ['increment_id' => (string) $order->getIncrementId()]
                );
                return ExportResult::fail('pancake_missing_id');
            }

            $this->fulfillmentLogger->info(
                self::SERVICE_CODE,
                'Pancake create-order succeeded.',
                [
                    'increment_id' => (string) $order->getIncrementId(),
                    'source_code' => $primary,
                ]
            );

            return ExportResult::ok($externalId);
        } catch (PosClientException $e) {
            $this->fulfillmentLogger->error(
                self::SERVICE_CODE,
                'Pancake create-order failed.',
                ['increment_id' => (string) $order->getIncrementId(), 'error' => $e->getMessage()]
            );
            return ExportResult::fail($e->getMessage());
        }
    }

    /**
     * @param array<string, mixed> $response
     */
    /**
     * Prefer the POS numeric id. Ignore values that only echo Magento custom_id / increment_id.
     *
     * @param array<string, mixed> $response
     */
    private function extractOrderId(array $response): ?string
    {
        $customId = $this->nestedString($response, 'custom_id');
        $candidates = [];
        foreach ([$response['data'] ?? null, $response['order'] ?? null, $response] as $node) {
            if (!is_array($node)) {
                continue;
            }
            foreach (['id', 'order_id'] as $key) {
                if (isset($node[$key]) && $node[$key] !== '') {
                    $candidates[] = (string) $node[$key];
                }
            }
        }

        foreach ($candidates as $id) {
            if ($customId !== null && $id === $customId) {
                continue;
            }
            if (ctype_digit($id)) {
                return $id;
            }
        }
        foreach ($candidates as $id) {
            if ($customId === null || $id !== $customId) {
                return $id;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $response
     */
    private function nestedString(array $response, string $key): ?string
    {
        if (isset($response[$key]) && $response[$key] !== '') {
            return (string) $response[$key];
        }
        foreach (['data', 'order'] as $node) {
            if (isset($response[$node][$key]) && is_array($response[$node]) && $response[$node][$key] !== '') {
                return (string) $response[$node][$key];
            }
        }

        return null;
    }
}

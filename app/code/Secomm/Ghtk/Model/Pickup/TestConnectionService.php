<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Secomm\Ghtk\Model\Pickup;

use Secomm\Ghtk\Model\Config\GhtkConfig;
use Secomm\Ghtk\Model\GhtkApiClient;
use Secomm\Ghtk\Model\GhtkApiException;
use Secomm\ShippingCore\Api\Http\CarrierHttpErrorCategory;

/**
 * TASK-3HPB76 — the GHTK Test Connection service (admin/ops tooling ONLY — it
 * is never called from `collectRates`, CREATE, or any checkout path).
 *
 * Flow (§3):
 *
 *   GET /services/shipment/list_pick_add   (read-only; safeRead retry at the client)
 *     ├── transport CLIENT_ERROR (403/400) → AUTH_FAILED  — "Check API token / X-Client-Source"
 *     ├── transport NETWORK/SERVER/TIMEOUT/INVALID → TECHNICAL_FAILURE
 *     ├── success=false / data unusable    → TECHNICAL_FAILURE (unusable technical data)
 *     └── success + list parsed
 *           ├── no configured pick_address_id → CONNECTED ("No pickup address ID configured")
 *           ├── configured ID ∈ list (exact)  → CONNECTED (+ pickup name diagnostic)
 *           └── configured ID ∉ list          → PICKUP_ID_INVALID (never first-pickup fallback)
 *
 * Legacy text-only pickup config (pick_province/pick_ward, no ID) passes the
 * connection test — the ID is only validated when configured.
 */
class TestConnectionService
{
    public function __construct(
        private readonly GhtkApiClient $apiClient,
        private readonly GhtkConfig $config
    ) {
    }

    public function test(?int $storeId): TestConnectionResult
    {
        $configuredId = $this->config->getPickAddressId($storeId);

        try {
            $response = $this->apiClient->getPickupAddresses($storeId);
        } catch (GhtkApiException $e) {
            if ($e->getCategory() === CarrierHttpErrorCategory::CLIENT_ERROR) {
                // 403 invalid/expired token (empty body), 400 — merchant auth/config.
                return TestConnectionResult::authFailed(
                    'GHTK authentication failed. Check the API token and X-Client-Source.',
                    $configuredId !== '' ? $configuredId : null
                );
            }

            return TestConnectionResult::technicalFailure(
                'GHTK is currently unreachable: ' . $e->getMessage(),
                $configuredId !== '' ? $configuredId : null
            );
        }

        if (($response['success'] ?? null) !== true || !is_array($response['data'] ?? null)) {
            // HTTP 200 but an unusable technical response (§12 of the probe directive).
            return TestConnectionResult::technicalFailure(
                'GHTK returned an unusable pickup-list response.',
                $configuredId !== '' ? $configuredId : null
            );
        }

        $list = GhtkPickupList::fromResponse($response);

        if ($configuredId === '') {
            return TestConnectionResult::connected(
                sprintf('GHTK connection OK — %d pickup address(es) on file. No pickup address ID configured.', $list->count()),
                $list->count()
            );
        }

        $match = $list->getById($configuredId);
        if ($match !== null) {
            return TestConnectionResult::connected(
                sprintf(
                    'GHTK connection OK — configured pickup ID valid: %s%s (%d pickup address(es) on file).',
                    $configuredId,
                    $match->name !== null ? ' — ' . $match->name : '',
                    $list->count()
                ),
                $list->count(),
                $configuredId,
                $match->name
            );
        }

        // §13/§14 — never fall back to the first returned pickup; merchant fixes the config.
        return TestConnectionResult::pickupIdInvalid(
            sprintf(
                'The configured GHTK pickup address ID "%s" was not found in the merchant pickup list (%d on file).',
                $configuredId,
                $list->count()
            ),
            $configuredId,
            $list->count()
        );
    }
}

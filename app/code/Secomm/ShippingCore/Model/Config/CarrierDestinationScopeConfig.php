<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Model\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Psr\Log\LoggerInterface;
use Secomm\ShippingCore\Api\Address\CanonicalZoneRegistryInterface;
use Secomm\ShippingCore\Api\Address\DestinationScope;
use Secomm\ShippingCore\Api\Config\CarrierDestinationScopeConfigInterface;

/**
 * FEAT-QA23PZ / DEC-FEATQA23PZ-001 — generic carrier destination-scope reader. All
 * carrier-specific knowledge is the `carriers/<code>/...` path prefix built from the
 * carrier code the caller already owns — no carrier conditionals here (architecture §35.9).
 */
class CarrierDestinationScopeConfig implements CarrierDestinationScopeConfigInterface
{
    private const XML_PATH_DESTINATION_SCOPE = 'carriers/%s/destination_scope';

    private const XML_PATH_ALLOWED_ZONE_CODES = 'carriers/%s/allowed_zone_codes';

    private ScopeConfigInterface $scopeConfig;

    private CanonicalZoneRegistryInterface $zoneRegistry;

    private LoggerInterface $logger;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        CanonicalZoneRegistryInterface $zoneRegistry,
        LoggerInterface $logger
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->zoneRegistry = $zoneRegistry;
        $this->logger = $logger;
    }

    public function getDestinationScope(string $carrierCode, ?int $storeId = null): string
    {
        $raw = $this->scopeConfig->getValue(
            sprintf(self::XML_PATH_DESTINATION_SCOPE, $carrierCode),
            $storeId !== null ? \Magento\Store\Model\ScopeInterface::SCOPE_STORE : ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
            $storeId
        );
        $scope = trim((string) $raw);
        if ($scope === '') {
            // Missing value — the documented product default applies (backward compatible);
            // this is NOT an invalid explicit value.
            return DestinationScope::ALL;
        }
        if (!DestinationScope::exists($scope)) {
            // TASK-R8WR1R r2 — an invalid EXPLICIT value fails CLOSED: the raw value is
            // returned verbatim (never coerced to a valid scope) and the eligibility
            // evaluator's unknown-scope branch stops the carrier before any coverage opens.
            // The previous coercion to ALL was fail-OPEN — broken coverage config would have
            // made the carrier eligible for every destination.
            $this->logger->warning(
                sprintf(
                    'Secomm_ShippingCore: unrecognized destination scope "%s" for carrier "%s"; failing closed (carrier ineligible).',
                    $scope,
                    $carrierCode
                ),
                ['scope' => $scope, 'carrier' => $carrierCode]
            );
        }

        return $scope;
    }

    public function getAllowedZoneCodes(string $carrierCode, ?int $storeId = null): array
    {
        $raw = $this->scopeConfig->getValue(
            sprintf(self::XML_PATH_ALLOWED_ZONE_CODES, $carrierCode),
            $storeId !== null ? \Magento\Store\Model\ScopeInterface::SCOPE_STORE : ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
            $storeId
        );
        $codes = $this->parseCodes($raw);
        $scope = $this->getDestinationScope($carrierCode, $storeId);
        if ($scope === DestinationScope::SELECTED_ZONES) {
            $this->diagnoseZoneReferences($carrierCode, $codes, false);
        } elseif ($scope === DestinationScope::ALL_EXCEPT_SELECTED_ZONES) {
            $this->diagnoseZoneReferences($carrierCode, $codes, true);
        }

        return $codes;
    }

    /**
     * @return string[] trim + drop empties + dedupe, input order preserved
     */
    private function parseCodes(mixed $raw): array
    {
        $parts = [];
        if (is_array($raw)) {
            $parts = $raw;
        } elseif (is_string($raw) && trim($raw) !== '') {
            $parts = explode(',', $raw);
        }
        $codes = [];
        foreach ($parts as $part) {
            $code = trim((string) $part);
            if ($code !== '' && !in_array($code, $codes, true)) {
                $codes[] = $code;
            }
        }

        return $codes;
    }

    /**
     * §16/§22 diagnostics — disabled and deleted zone references never match silently:
     * the evaluator's no-match semantics stay untouched, the operational noise lands here.
     * Under ALL_EXCEPT_SELECTED_ZONES ($exclusionMode) the references do not exclude either —
     * the hint names the actual effect so operators are not misled (TASK-R8WR1R).
     */
    private function diagnoseZoneReferences(string $carrierCode, array $codes, bool $exclusionMode): void
    {
        $unknownHint = $exclusionMode ? '(does not exclude)' : '(no match)';
        $disabledHint = $exclusionMode ? '(not matching — does not exclude)' : '(not matching)';
        foreach ($codes as $code) {
            $zone = $this->zoneRegistry->getByCode($code);
            if ($zone === null) {
                $this->logger->warning(
                    sprintf(
                        'Secomm_ShippingCore: carrier "%s" references unknown zone "%s" %s.',
                        $carrierCode,
                        $code,
                        $unknownHint
                    ),
                    ['carrier' => $carrierCode, 'zone' => $code]
                );
                continue;
            }
            if (!$zone->isEnabled()) {
                $this->logger->warning(
                    sprintf(
                        'Secomm_ShippingCore: carrier "%s" references disabled zone "%s" %s.',
                        $carrierCode,
                        $code,
                        $disabledHint
                    ),
                    ['carrier' => $carrierCode, 'zone' => $code]
                );
            }
        }
    }
}

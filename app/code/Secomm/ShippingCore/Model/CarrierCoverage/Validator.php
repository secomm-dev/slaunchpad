<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Model\CarrierCoverage;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;
use Secomm\ShippingCore\Api\Address\AddressResolutionPolicy;
use Secomm\ShippingCore\Api\Address\CanonicalZoneRegistryInterface;
use Secomm\ShippingCore\Api\Rate\RateSourceMode;

/**
 * FEAT-QA23PZ / TASK-G3K9V2 — server-side validation for the Shipping Coverage save
 * (replaces the deleted GHN backend model, semantics mirrored):
 * availability ∈ enum; zone-requiring modes need ≥1 zone; every zone reference must EXIST
 * (deleted references rejected — §10/§16; DISABLED ones allowed, they fail safe at runtime
 * with a diagnostic); rate source mode + address resolution policy ∈ their contract enums.
 * Zone references are codes only — provider IDs never enter carrier config from this UI.
 */
final class Validator
{
    private CanonicalZoneRegistryInterface $zoneRegistry;

    public function __construct(CanonicalZoneRegistryInterface $zoneRegistry)
    {
        $this->zoneRegistry = $zoneRegistry;
    }

    /**
     * @param string $availability Availability::* value
     * @param string[] $zoneCodes canonical zone codes
     * @param string $rateSourceMode RateSourceMode::* value
     * @param string $addressResolutionPolicy AddressResolutionPolicy::* value
     * @return string[] normalized (trim + drop empty + dedupe) zone codes
     * @throws LocalizedException first failure wins
     */
    public function validate(
        string $availability,
        array $zoneCodes,
        string $rateSourceMode,
        string $addressResolutionPolicy
    ): array {
        if (!Availability::exists($availability)) {
            throw new LocalizedException(new Phrase(
                'Unknown availability "%1". Known values: %2.',
                [$availability, implode(', ', Availability::all())]
            ));
        }
        if (!RateSourceMode::exists($rateSourceMode)) {
            throw new LocalizedException(new Phrase(
                'Unknown Rate Source Mode "%1". Known modes: %2.',
                [$rateSourceMode, implode(', ', RateSourceMode::all())]
            ));
        }
        if (!AddressResolutionPolicy::exists($addressResolutionPolicy)) {
            throw new LocalizedException(new Phrase(
                'Unknown Address Resolution Policy "%1". Known policies: %2.',
                [$addressResolutionPolicy, implode(', ', AddressResolutionPolicy::all())]
            ));
        }

        $codes = [];
        foreach ($zoneCodes as $code) {
            $code = trim((string) $code);
            if ($code !== '' && !in_array($code, $codes, true)) {
                $codes[] = $code;
            }
        }

        if (Availability::requiresZones($availability)) {
            if ($codes === []) {
                throw new LocalizedException(new Phrase(
                    'Availability "%1" requires at least one selected zone.',
                    [$availability]
                ));
            }
            foreach ($codes as $code) {
                if ($this->zoneRegistry->getByCode($code) === null) {
                    throw new LocalizedException(new Phrase(
                        'Zone "%1" does not exist (it may have been deleted). Re-select the zones.',
                        [$code]
                    ));
                }
            }
        }

        return $codes;
    }
}

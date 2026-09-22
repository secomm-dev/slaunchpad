<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Model\Address;

use Magento\Quote\Model\Quote\Address;
use Secomm\ShippingCore\Api\Address\CarrierAddressCapabilityInterface;
use Secomm\ShippingCore\Api\Address\CarrierOperationAddressCapabilityInterface;
use Secomm\ShippingCore\Api\Address\DestinationContextBuilderInterface;
use Secomm\ShippingCore\Api\Address\RuntimeAddressContextBuilderInterface;
use Secomm\ShippingCore\Api\Address\ShippingAddressResolutionContextInterface;

/**
 * TASK-T78YH6 (Phase E-C0) — @see DestinationContextBuilderInterface.
 *
 * TASK-7AJ3K8: translation now DELEGATES to the scalar runtime context builder (the single
 * identity path shared with non-quote address sources): id-based bridge first, name-based
 * (native `city` locality) fallback, AMBIGUOUS candidates passed through untouched. This class
 * contributes only the quote-specific fields: country, street text (PII hygiene — no recipient
 * name/phone/email) and the capability target scheme.
 */
final class DestinationContextBuilder implements DestinationContextBuilderInterface
{
    public function __construct(
        private readonly RuntimeAddressContextBuilderInterface $runtimeContextBuilder
    ) {
    }

    /**
     * @inheritDoc
     */
    public function build(
        Address $destination,
        CarrierAddressCapabilityInterface $capability
    ): ShippingAddressResolutionContextInterface {
        return $this->runtimeContextBuilder->build(
            $destination->getCountryId(),
            (int) $destination->getRegionId(),
            (int) $destination->getData('city_id') ?: null,
            $destination->getCity(),
            $capability,
            $this->extractStreetText($destination)
        );
    }

    /**
     * @inheritDoc
     */
    public function buildForOperation(
        Address $destination,
        CarrierOperationAddressCapabilityInterface $capability,
        string $operation
    ): ShippingAddressResolutionContextInterface {
        // The per-op capability is adapted to the legacy per-carrier shape — the runtime
        // context builder reads getRequiredScheme() and stays operation-agnostic.
        return $this->runtimeContextBuilder->build(
            $destination->getCountryId(),
            (int) $destination->getRegionId(),
            (int) $destination->getData('city_id') ?: null,
            $destination->getCity(),
            new OperationCapabilityAdapter($capability, $operation),
            $this->extractStreetText($destination)
        );
    }

    /**
     * Street/address text is kept for the future external-disambiguation flow; it is never part
     * of the canonical lookup key. Recipient name/phone/email are NOT extracted (PII hygiene).
     */
    private function extractStreetText(Address $destination): ?string
    {
        $lines = array_filter(
            (array) $destination->getStreet(),
            static fn ($line): bool => is_string($line) && trim($line) !== ''
        );
        if ($lines === []) {
            return null;
        }

        return implode(', ', array_map('trim', array_values($lines)));
    }
}

<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Model\Address;

use Secomm\ShippingCore\Api\Address\ShippingAddressResolutionContextInterface;

/**
 * TASK-AQT7V3 — immutable scalar context DTO; @see ShippingAddressResolutionContextInterface.
 */
final class ShippingAddressResolutionContext implements ShippingAddressResolutionContextInterface
{
    private array $candidateCodes = [];

    /**
     * @param string|null $countryId ISO country id of the destination
     * @param string|null $sourceScheme canonical scheme of the current identity
     * @param string|null $sourceUnitCode canonical unit code of the current identity
     * @param string $targetScheme required non-empty
     * @param string|null $streetText full shipping street/address text (external disambiguation hint)
     * @param string[] $candidateCodes AMBIGUOUS candidates awaiting disambiguation (order preserved)
     * @throws \LogicException on an empty target scheme or a non-string candidate code
     */
    public function __construct(
        private readonly ?string $countryId,
        private readonly ?string $sourceScheme,
        private readonly ?string $sourceUnitCode,
        private readonly string $targetScheme,
        private readonly ?string $streetText = null,
        array $candidateCodes = []
    ) {
        if (trim($targetScheme) === '') {
            throw new \LogicException('Shipping address resolution context requires a non-empty target scheme.');
        }
        foreach ($candidateCodes as $candidateCode) {
            if (!is_string($candidateCode) || trim($candidateCode) === '') {
                throw new \LogicException('Candidate codes must be non-empty strings.');
            }
        }
        $this->candidateCodes = array_values($candidateCodes);
    }

    public function getCountryId(): ?string
    {
        return $this->countryId;
    }

    public function getSourceScheme(): ?string
    {
        return $this->sourceScheme;
    }

    public function getSourceUnitCode(): ?string
    {
        return $this->sourceUnitCode;
    }

    public function getTargetScheme(): string
    {
        return $this->targetScheme;
    }

    public function getStreetText(): ?string
    {
        return $this->streetText;
    }

    public function getCandidateCodes(): array
    {
        return $this->candidateCodes;
    }
}

<?php
declare(strict_types=1);
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\VietNamAddress\Model;

use Magento\Framework\Exception\LocalizedException;
use Secomm\VietNamAddress\Api\Data\VnAddressResolutionInterface;
use Secomm\VietNamAddress\Api\VnAdminAddressResolverInterface;
use Secomm\VietNamAddress\Api\VnAddressUnitProviderInterface;
use Secomm\VietNamAddress\Model\Data\VnAddressResolutionData;
use Secomm\VietNamAddress\Model\Scheme\VnSchemes;

/**
 * TASK-J9AVGK — directional resolution over the administrative mapping layer.
 * See VnAdminAddressResolverInterface for the status semantics.
 */
class VnAdminAddressResolver implements VnAdminAddressResolverInterface
{
    public function __construct(
        private readonly VnAddressUnitProviderInterface $unitProvider,
        private readonly MappingCandidateFinder $candidateFinder
    ) {
    }

    /**
     * @inheritDoc
     */
    public function resolve(string $sourceScheme, string $sourceCode, string $targetScheme): VnAddressResolutionInterface
    {
        VnSchemes::assertKnown($sourceScheme);
        VnSchemes::assertKnown($targetScheme);

        $sourceUnit = $this->unitProvider->getUnit($sourceScheme, $sourceCode);

        if ($sourceScheme === $targetScheme) {
            return $sourceUnit !== null
                ? $this->result($sourceScheme, $sourceCode, $targetScheme, VnAddressResolutionInterface::STATUS_EXACT, $sourceCode, null, [], null)
                : $this->unmapped($sourceScheme, $sourceCode, $targetScheme, VnAddressResolutionInterface::REASON_UNKNOWN_SOURCE_UNIT);
        }

        $candidates = $this->candidateFinder->find($sourceScheme, $sourceCode, $targetScheme);

        if (count($candidates) === 1) {
            return $this->result(
                $sourceScheme,
                $sourceCode,
                $targetScheme,
                VnAddressResolutionInterface::STATUS_MAPPED,
                $candidates[0]['code'],
                $candidates[0]['relation_type'],
                [],
                null
            );
        }

        if (count($candidates) > 1) {
            $codes = array_unique(array_map(static fn (array $row): string => (string)$row['code'], $candidates));
            sort($codes);

            return $this->result(
                $sourceScheme,
                $sourceCode,
                $targetScheme,
                VnAddressResolutionInterface::STATUS_AMBIGUOUS,
                null,
                null,
                $codes,
                null
            );
        }

        return $sourceUnit !== null
            ? $this->unmapped($sourceScheme, $sourceCode, $targetScheme, VnAddressResolutionInterface::REASON_NO_MAPPING)
            : $this->unmapped($sourceScheme, $sourceCode, $targetScheme, VnAddressResolutionInterface::REASON_UNKNOWN_SOURCE_UNIT);
    }

    /**
     * @param string[] $candidateCodes
     */
    private function result(
        string $sourceScheme,
        string $sourceCode,
        string $targetScheme,
        string $status,
        ?string $resolvedCode,
        ?string $relationType,
        array $candidateCodes,
        ?string $reason
    ): VnAddressResolutionInterface {
        return new VnAddressResolutionData(
            $sourceScheme,
            $sourceCode,
            $targetScheme,
            $status,
            $resolvedCode,
            $relationType,
            $candidateCodes,
            $reason
        );
    }

    private function unmapped(string $sourceScheme, string $sourceCode, string $targetScheme, string $reason): VnAddressResolutionInterface
    {
        return $this->result($sourceScheme, $sourceCode, $targetScheme, VnAddressResolutionInterface::STATUS_UNMAPPED, null, null, [], $reason);
    }
}

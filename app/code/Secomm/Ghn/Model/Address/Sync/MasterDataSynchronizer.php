<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Model\Address\Sync;

use Magento\Framework\Exception\LocalizedException;
use Secomm\Ghn\Model\Address\GhnSchemes;
use Secomm\Ghn\Model\Config;

/**
 * SPEC-FEAT-FQWEQ3 §8 — CLI-only master data synchronization from the GHN APIs (never inside a
 * checkout request). Persistence rules live in UnitPersister (shared with the GHN-B2 file
 * importer); a provider sync NEVER touches secomm_ghn_address_mapping (DEC-FEATFQWEQ3-002 §5 —
 * reviewed mapping decisions are not overwritten by a provider sync).
 */
class MasterDataSynchronizer
{
    public function __construct(
        /** @var array<string, MasterDataFetcher> scheme => fetcher (di.xml) */
        private readonly array $fetchers,
        private readonly UnitPersister $unitPersister,
        private readonly Config $config
    ) {
    }

    /**
     * @return array{
     *     scheme: string, dry_run: bool, fetched: int, by_depth: array<int, int>,
     *     present_before: int, disabled: int, source_version: string
     * }
     * @throws LocalizedException unknown scheme / no fetcher / duplicate provider_key / transport failure
     */
    public function sync(string $scheme, bool $dryRun): array
    {
        GhnSchemes::assertKnown($scheme);
        $fetcher = $this->fetchers[$scheme]
            ?? throw new LocalizedException(__('No master-data fetcher registered for scheme %1.', $scheme));

        $rows = $fetcher->fetch($scheme);

        $byDepth = [];
        foreach ($rows as $row) {
            $byDepth[(int) $row['depth']] = ($byDepth[(int) $row['depth']] ?? 0) + 1;
        }
        ksort($byDepth);

        $sourceVersion = $this->config->getEnvironment() . ':' . gmdate('Y-m-d');
        $presentBefore = count($this->unitPersister->knownKeys($scheme));

        $result = $this->unitPersister->persist($scheme, $rows, $sourceVersion, $dryRun);

        return [
            'scheme' => $scheme,
            'dry_run' => $dryRun,
            'fetched' => count($rows),
            'by_depth' => $byDepth,
            'present_before' => $presentBefore,
            'disabled' => $result['disabled'],
            'source_version' => $sourceVersion,
        ];
    }
}

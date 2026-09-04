<?php
declare(strict_types=1);
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\VietNamAddress\Model\OptionSource;

use Magento\Framework\Data\OptionSourceInterface;
use Secomm\VietNamAddress\Model\Scheme\VnSchemeRegistry;
use Secomm\VietNamAddress\Model\Scheme\VnSchemes;

/**
 * DEC-FEATYA2C0W-003 — option source for secomm_vietnam_address/general/active_scheme.
 * Values are canonical scheme codes; the status (CURRENT/HISTORICAL/FUTURE) is appended
 * to the LABEL only — it is never part of the value.
 */
class VnAdminScheme implements OptionSourceInterface
{
    public function __construct(
        private readonly VnSchemeRegistry $registry
    ) {
    }

    /**
     * @inheritDoc
     */
    public function toOptionArray(): array
    {
        $registryRows = $this->registry->getAll();
        $statuses = [];
        foreach ($registryRows as $row) {
            $statuses[$row['scheme_code']] = $row['status'];
        }

        $options = [];
        foreach (VnSchemes::catalog() as $scheme => $entry) {
            $status = $statuses[$scheme] ?? null;
            $options[] = [
                'value' => $scheme,
                'label' => $status !== null
                    ? sprintf('%s (%s)', $entry['label'], strtolower($status))
                    : $entry['label'] . ' (not installed)',
            ];
        }

        return $options;
    }
}

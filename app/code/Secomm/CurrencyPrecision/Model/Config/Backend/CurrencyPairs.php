<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\CurrencyPrecision\Model\Config\Backend;

use Magento\Framework\App\Config\Value;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Phrase;

/**
 * Validates the CODE=PRECISION override list before save (e.g. "VND=0, USD=2").
 */
class CurrencyPairs extends Value
{
    private const PAIR_PATTERN = '/^([A-Z]{3})\s*=\s*([0-4])$/i';

    /**
     * Reject malformed pairs at save time instead of silently ignoring them
     * at runtime — a typo'd config must never change display half-way.
     *
     * @throws LocalizedException
     * @throws FileSystemException
     */
    public function beforeSave(): parent
    {
        $raw = trim((string)$this->getValue());
        if ($raw === '') {
            return parent::beforeSave();
        }

        foreach (explode(',', $raw) as $pair) {
            $pair = trim($pair);
            if ($pair === '') {
                continue;
            }
            if (preg_match(self::PAIR_PATTERN, $pair) !== 1) {
                throw new LocalizedException(new Phrase(
                    'Invalid currency precision pair "%1". Expected CODE=0..4, e.g. VND=0, USD=2.',
                    [$pair]
                ));
            }
        }

        return parent::beforeSave();
    }
}

<?php
declare(strict_types=1);
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\AddressDropdown\Model\OptionSource;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * FEAT-2PZQKJ / TASK-3T3NSV — renderer selection flag (A/B cutover, Phase 2):
 * legacy = the fixed region→city cascade templates (default);
 * schema = the schema-driven generic renderer (behind this per-store flag).
 * (TASK-6MKF0V: sub_city wording removed — depth-2 rendering belongs to the schema renderer.)
 */
class RendererMode implements OptionSourceInterface
{
    public const MODE_LEGACY = 'legacy';
    public const MODE_SCHEMA = 'schema';

    /**
     * @inheritDoc
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => self::MODE_LEGACY, 'label' => __('Legacy (fixed region → city cascade)')],
            ['value' => self::MODE_SCHEMA, 'label' => __('Schema-driven (Address Profile cascade)')],
        ];
    }
}

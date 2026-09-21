<?php
/*
 * TASK-5XQXZK (DEC-TASK5XQXZK-001) — City/Area constraint persistence on rate save.
 * TASK-JZXM66 — region/city consistency guard.
 * TL review round — validation moved BEFORE Mageplaza rate persistence: a rejected
 * City/Area must NOT leave a partially-saved rate row (no partial-save semantics).
 *
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Launchpad\MageplazaTableRate\Plugin\Rate;

use Launchpad\MageplazaTableRate\Model\Adminhtml\SettingsPersister;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\AbstractModel;

/**
 * City/Area constraint lifecycle for the admin rate form (formData carries `city_code`):
 *
 *  - BEFORE Mageplaza persists the rate row: validate the posted code (existence +
 *    region consistency when a concrete region is posted). A rejection aborts the save —
 *    no rate row without its declared City/Area constraint ever lands (no partial save).
 *  - AFTER the (now validated) save: persist/remove the constraint row. Post-save failures
 *    are impossible by construction (the code was validated immediately before), so the
 *    persister call is intentionally left throwing — a race there must surface, not hide.
 *
 * The CSV importer strips `city_code` from the rate object (it validates + persists the
 * constraint itself), so `beforeSave`/`afterSave` are inert on the import path — the plugin
 * guards ONLY the admin form path.
 *
 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
 */
class ResourceRateSavePlugin
{
    public function __construct(
        private readonly SettingsPersister $persister
    ) {
    }

    /**
     * Validate BEFORE the rate row is written — rejection aborts the whole save.
     *
     * @throws LocalizedException unknown code / region mismatch
     */
    public function beforeSave(
        \Mageplaza\TableRateShipping\Model\ResourceModel\Rate $subject,
        AbstractModel $object
    ): array {
        $cityCode = trim((string) ($object->getData('city_code') ?? ''));
        if ($cityCode === '') {
            return [$object];
        }

        if (!$this->persister->cityCodeExists($cityCode)) {
            throw new LocalizedException(
                __('City / Area code "%1" does not exist in the address hierarchy — save a valid code.', $cityCode)
            );
        }

        $regionId = $this->postedRegionId($object->getData('region'));
        if ($regionId > 0 && !$this->persister->cityBelongsToRegion($cityCode, $regionId)) {
            throw new LocalizedException(
                __('City / Area "%1" does not belong to the selected region.', $cityCode)
            );
        }

        return [$object];
    }

    /**
     * Persist/remove the constraint row after the validated save (empty code = wildcard).
     */
    public function afterSave(
        \Mageplaza\TableRateShipping\Model\ResourceModel\Rate $subject,
        $result,
        AbstractModel $object
    ) {
        $cityCode = $object->getData('city_code');
        if ($cityCode !== null) {
            $this->persister->saveRateCity((int) $object->getId(), (string) $cityCode);
        }

        return $result;
    }

    /**
     * Posted region as a concrete region_id — Mageplaza normalizes empty to `*`; anything
     * non-numeric (`*`, region code that never resolved) means "no region to check against".
     */
    private function postedRegionId(mixed $region): int
    {
        $region = trim((string) ($region ?? ''));

        return ctype_digit($region) ? (int) $region : 0;
    }
}

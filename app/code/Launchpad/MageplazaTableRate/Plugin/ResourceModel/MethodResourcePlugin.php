<?php
/*
 * TASK-SEC-C1 — persist Fallback settings on the RESOURCE seam, where the persisted
 * method identity exists for both CREATE and UPDATE.
 *
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Launchpad\MageplazaTableRate\Plugin\ResourceModel;

use Launchpad\MageplazaTableRate\Model\Adminhtml\SettingsCapture;
use Launchpad\MageplazaTableRate\Model\Adminhtml\SettingsPersister;
use Magento\Framework\Model\AbstractModel;

/**
 * afterSave: a captured payload is persisted against the JUST-SAVED model's own id —
 * the canonical persisted identity (never MAX(id)/latest-row). A persistence failure
 * throws INTO the vendor save try-block, whose catch shows the message and restores the
 * form session — the admin sees a real error, never a silent or false success
 * (documented partial-save semantics: method saved, settings error surfaced, form data kept).
 *
 * afterDelete is DELIBERATELY ABSENT (TASK-SEC-C1 review): both extension tables carry
 * FK ON DELETE CASCADE to the method — the database is the single cleanup owner; a plugin
 * delete would create a second ownership path over the same rows.
 */
class MethodResourcePlugin
{
    public function __construct(
        private readonly SettingsCapture $capture,
        private readonly SettingsPersister $persister
    ) {
    }

    /**
     * @param \Mageplaza\TableRateShipping\Model\ResourceModel\Method $subject
     * @param mixed $result
     * @param AbstractModel $object the just-saved method (has its id after save)
     * @return mixed
     */
    public function afterSave($subject, $result, AbstractModel $object)
    {
        $payload = $this->capture->consume();
        if ($payload !== null) {
            $this->persister->saveMethodSettings((int) $object->getId(), $payload);
        }

        return $result;
    }
}

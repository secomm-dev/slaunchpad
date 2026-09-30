<?php
/*
 * TASK-5XQXZK — persist Fallback settings after Mageplaza method save.
 * TASK-SEC-C1 — CREATE methods carry no `method_id` in the request, so the historical
 * afterExecute persister silently dropped the posted Fallback tab. The payload is now
 * CAPTURED here and persisted by the resource-layer plugin (MethodResourcePlugin::afterSave),
 * which reads the REAL persisted id off the saved model — create and update share one path,
 * no latest-row guessing, vendor untouched.
 *
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Launchpad\MageplazaTableRate\Plugin\Adminhtml;

use Launchpad\MageplazaTableRate\Model\Adminhtml\SettingsCapture;
use Magento\Framework\App\RequestInterface;

class MethodSavePlugin
{
    public function __construct(
        private readonly SettingsCapture $capture,
        private readonly RequestInterface $request
    ) {
    }

    /**
     * Capture the posted Launchpad payload before the controller runs; only an actual
     * array payload is captured (no payload = keep native behavior AND existing state).
     *
     * @param \Mageplaza\TableRateShipping\Controller\Adminhtml\Method\Save $subject
     * @return array
     */
    public function beforeExecute($subject): array
    {
        $payload = $this->request->getParam('launchpad');
        if (is_array($payload)) {
            $this->capture->capture($payload);
        }

        return [];
    }

    /**
     * @param mixed $result redirect result, passed through untouched
     * @return mixed
     */
    public function afterExecute($subject, $result)
    {
        return $result;
    }
}

<?php
/*
 * TASK-5XQXZK (DEC-TASK5XQXZK-001) — persist Launchpad settings after Mageplaza method save.
 *
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Launchpad\MageplazaTableRate\Plugin\Adminhtml;

use Launchpad\MageplazaTableRate\Model\Adminhtml\SettingsPersister;
use Magento\Framework\App\RequestInterface;

/**
 * Runs after Mageplaza's method Save controller finishes: reads the `launchpad[...]` payload
 * (posted only by the Launchpad tab) and persists capabilities + membership through the shared
 * persister. Mageplaza's own save path is untouched — its resource layer already filters the
 * unknown `launchpad` key out of its own tables.
 *
 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
 */
class MethodSavePlugin
{
    public function __construct(
        private readonly SettingsPersister $persister,
        private readonly RequestInterface $request
    ) {
    }

    /**
     * @param \Mageplaza\TableRateShipping\Controller\Adminhtml\Method\Save $subject
     * @param mixed $result redirect result, passed through untouched
     * @return mixed
     */
    public function afterExecute($subject, $result)
    {
        $methodId = (int) $this->request->getParam('method_id');
        if ($methodId > 0) {
            $this->persister->saveMethodSettings($methodId, $this->request->getParam('launchpad'));
        }

        return $result;
    }
}

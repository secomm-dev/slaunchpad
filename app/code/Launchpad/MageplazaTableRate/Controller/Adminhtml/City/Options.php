<?php
/*
 * TASK-JZXM66 — AJAX feed for the rate-form City/Area cascading select: the coded nodes of one
 * region (wildcard entry always first).
 *
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Launchpad\MageplazaTableRate\Controller\Adminhtml\City;

use Launchpad\MageplazaTableRate\Controller\Adminhtml\City;
use Launchpad\MageplazaTableRate\Model\MethodSettingsProvider;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;

/**
 * GET launchpad_mptablerate/city/options?region=<region_id> →
 * `{"options": [{"code": "", "label": "All / *"}, {"code": "VNA25-…", "label": "…"}, …]}`.
 * ACL `Mageplaza_TableRateShipping::method`; AJAX-only (noroute otherwise).
 */
class Options extends City
{
    public function __construct(
        Context $context,
        private readonly MethodSettingsProvider $settingsProvider,
        private readonly JsonFactory $resultJsonFactory
    ) {
        parent::__construct($context);
    }

    /**
     * @return ResultInterface|null null when forwarded to noroute (non-AJAX request)
     */
    public function execute(): ?ResultInterface
    {
        if (!$this->getRequest()->isAjax()) {
            $this->_forward('noroute');

            return null;
        }

        $regionId = (int) $this->getRequest()->getParam('region');
        $options = $this->settingsProvider->fetchCityOptionsByRegion($regionId);

        /** @var Json $result */
        $result = $this->resultJsonFactory->create();

        return $result->setData(['options' => $options]);
    }
}

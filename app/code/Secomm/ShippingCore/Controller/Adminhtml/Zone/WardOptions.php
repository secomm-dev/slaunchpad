<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Controller\Adminhtml\Zone;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Secomm\VietNamAddress\Api\VnAddressUnitProviderInterface;
use Secomm\VietNamAddress\Model\Scheme\VnSchemes;

/**
 * FEAT-QA23PZ / DEC-FEATQA23PZ-001 — AJAX ward options constrained by the selected
 * canonical provinces (`?provinces=VN-01,VN-15`). Identity = canonical VN_ADMIN_2025 ward
 * codes; labels are display-only, "name (CODE)" (TASK-G3K9V2 — the admin never types codes).
 * Options resolve via `region_code` + level 2 (TASK-G3K9V2 — seeded rows carry NULL
 * parent_code, so parent-based lookups cannot serve this). Precedent:
 * Launchpad_MageplazaTableRate Controller\Adminhtml\City\Options. GET, AJAX-guarded.
 */
class WardOptions extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Secomm_ShippingCore::zones_manage';

    private JsonFactory $resultJsonFactory;

    private VnAddressUnitProviderInterface $unitProvider;

    public function __construct(Context $context, JsonFactory $resultJsonFactory, VnAddressUnitProviderInterface $unitProvider)
    {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->unitProvider = $unitProvider;
    }

    public function execute(): Json
    {
        if (!$this->getRequest()->isAjax()) {
            $this->_forward('noroute');

            return $this->resultJsonFactory->create();
        }
        $provinces = array_filter(array_map('trim', explode(',', (string) $this->getRequest()->getParam('provinces'))));
        $options = [];
        $seen = [];
        foreach ($provinces as $provinceCode) {
            foreach ($this->unitProvider->getByRegion(VnSchemes::VN_ADMIN_2025, $provinceCode, 2) as $ward) {
                $code = $ward->getCode();
                if (isset($seen[$code])) {
                    continue;
                }
                $seen[$code] = true;
                $name = $ward->getNameVi() !== '' ? $ward->getNameVi() : $ward->getNameEn();
                $options[] = [
                    'value' => $code,
                    'label' => sprintf('%s (%s)', $name, $code),
                ];
            }
        }

        return $this->resultJsonFactory->create()->setData(['options' => $options]);
    }
}

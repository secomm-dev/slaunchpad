<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Block\Adminhtml\Zone\Edit;

use Magento\Backend\Block\Widget\Context;
use Magento\Framework\Registry;
use Secomm\ShippingCore\Model\Zone as ZoneModel;

/**
 * FEAT-QA23PZ / DEC-FEATQA23PZ-001 — shared edit-context helpers for the form buttons.
 */
class GenericButton
{
    private Context $context;

    private Registry $registry;

    public function __construct(Context $context, Registry $registry)
    {
        $this->context = $context;
        $this->registry = $registry;
    }

    public function getZoneId(): ?int
    {
        $zone = $this->registry->registry(\Secomm\ShippingCore\Controller\Adminhtml\Zone\Edit::REGISTRY_KEY);

        return $zone instanceof ZoneModel && $zone->getId() ? (int) $zone->getId() : null;
    }

    public function getUrl(string $route, array $params = []): string
    {
        return $this->context->getUrlBuilder()->getUrl($route, $params);
    }
}

<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Block\Adminhtml\Coverage\Edit;

use Magento\Backend\Block\Widget\Context;
use Magento\Framework\Registry;
use Secomm\ShippingCore\Api\CoverageTarget\CoverageTargetInterface;
use Secomm\ShippingCore\Controller\Adminhtml\Coverage\Edit as EditAction;

/**
 * TASK-WY6WP5 — shared edit-context helpers for the coverage form buttons. The edit
 * controller registers the resolved CoverageTarget; create mode registers nothing.
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

    public function getTarget(): ?CoverageTargetInterface
    {
        $target = $this->registry->registry(EditAction::REGISTRY_KEY);

        return $target instanceof CoverageTargetInterface ? $target : null;
    }

    public function getUrl(string $route, array $params = []): string
    {
        return $this->context->getUrlBuilder()->getUrl($route, $params);
    }
}

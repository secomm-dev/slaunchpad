<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Block\Adminhtml\Coverage\Edit;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;
use Secomm\ShippingCore\Model\CarrierCoverage\CarrierCoverageConfigAdapter;

/**
 * TASK-WY6WP5 — RESET TO DEFAULTS button (directive §8): only offered while editing a
 * target that HAS an explicit coverage config. The three-argument deleteConfirm routes
 * through dataPost (form_key injected) → a real POST — the two-argument form would be a
 * GET, which the Reset controller refuses.
 */
class ResetButton extends Template implements ButtonProviderInterface
{
    private GenericButton $genericButton;

    private CarrierCoverageConfigAdapter $configAdapter;

    public function __construct(
        Template\Context $context,
        GenericButton $genericButton,
        CarrierCoverageConfigAdapter $configAdapter,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->genericButton = $genericButton;
        $this->configAdapter = $configAdapter;
    }

    public function getButtonData(): array
    {
        $target = $this->genericButton->getTarget();
        if ($target === null || !$this->configAdapter->hasExplicitConfig($target->getIdentity())) {
            return [];
        }
        $identity = $target->getIdentity();

        return [
            'label' => __('Reset to Defaults'),
            'class' => 'reset',
            'on_click' => sprintf(
                "deleteConfirm('%s', '%s', {data: {}})",
                __(
                    'Reset this coverage to defaults? The configuration values are removed and the target falls back to the documented runtime defaults. It stays registered.'
                ),
                $this->genericButton->getUrl('*/*/reset', [
                    'target_type' => $identity->type(),
                    'target_code' => $identity->code(),
                ])
            ),
            'sort_order' => 35,
        ];
    }
}

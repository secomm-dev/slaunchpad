<?php
/*
 * TASK-5XQXZK (DEC-TASK5XQXZK-001) — "Launchpad Settings" tab on the Mageplaza method form.
 *
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Launchpad\MageplazaTableRate\Block\Adminhtml\Method\Edit\Tab;

use Launchpad\MageplazaTableRate\Model\MethodSettingsProvider;
use Launchpad\MageplazaTableRate\Model\Source\ShippingMethod;
use Magento\Backend\Block\Widget\Form\Generic;
use Magento\Backend\Block\Widget\Tab\TabInterface;

/**
 * Per-method Launchpad capabilities (directive §16). Posted under the `launchpad` array key —
 * Mageplaza's Save controller copies the whole post into the method object, whose resource
 * layer filters unknown keys against the table columns, so this payload never reaches the
 * Mageplaza persistence and is consumed by the Launchpad Save plugin instead.
 */
class Launchpad extends Generic implements TabInterface
{
    private MethodSettingsProvider $settingsProvider;

    private ShippingMethod $shippingMethodSource;

    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Data\FormFactory $formFactory,
        MethodSettingsProvider $settingsProvider,
        ShippingMethod $shippingMethodSource,
        array $data = []
    ) {
        $this->settingsProvider = $settingsProvider;
        $this->shippingMethodSource = $shippingMethodSource;
        parent::__construct($context, $registry, $formFactory, $data);
    }

    protected function _construct()
    {
        parent::_construct();
        $this->setDestElementId('edit_form');
        $this->setTitle(__('Launchpad Settings'));
    }

    protected function _prepareForm()
    {
        $form = $this->_formFactory->create();
        $form->setHtmlIdPrefix('launchpad_');

        $fieldset = $form->addFieldset('launchpad_fieldset', ['legend' => __('Launchpad Settings')]);

        $methodId = (int) ($this->_coreRegistry->registry(\Mageplaza\TableRateShipping\Model\RegistryConstants::METHOD)?->getId() ?? 0);
        $settings = $this->settingsProvider->getSettingsMap()[$methodId] ?? null;

        $fieldset->addField('show_to_customer', 'select', [
            'name' => 'launchpad[show_to_customer]',
            'label' => __('Show to Customer'),
            'title' => __('Show to Customer'),
            'values' => [
                ['value' => 1, 'label' => __('Yes')],
                ['value' => 0, 'label' => __('No')],
            ],
            'value' => $settings ? (int) $settings->isShowToCustomer() : 1,
            'note' => __('No = this method is hidden from customers in normal checkout (fallback-only when Use as Fallback is Yes). Realtime carrier methods are never hidden by this setting.'),
        ]);

        $fieldset->addField('use_as_fallback', 'select', [
            'name' => 'launchpad[use_as_fallback]',
            'label' => __('Use as Fallback'),
            'title' => __('Use as Fallback'),
            'values' => [
                ['value' => 1, 'label' => __('Yes')],
                ['value' => 0, 'label' => __('No')],
            ],
            'value' => $settings ? (int) $settings->isUseAsFallback() : 0,
            'note' => __('Yes = when no assigned realtime method produced a usable rate (per the fallback eligibility policy), this method\'s table rate is offered as the emergency price.'),
        ]);

        $fieldset->addField('members', 'multiselect', [
            'name' => 'launchpad[members][]',
            'label' => __('Fallback Members'),
            'title' => __('Fallback Members'),
            'values' => $this->shippingMethodSource->toOptionArray(),
            'value' => $this->currentMemberValues($methodId),
            'note' => __('Realtime shipping methods that gate this fallback group. Only installed Magento carriers are listed; a member that is not part of the current rate collection never triggers the fallback by itself.'),
        ]);

        $this->setForm($form);

        return parent::_prepareForm();
    }

    /**
     * @return string[]
     */
    private function currentMemberValues(int $methodId): array
    {
        $values = [];
        foreach ($this->settingsProvider->getEnabledMembersMap()[$methodId] ?? [] as $member) {
            $values[] = $member['carrier_code'] . ShippingMethod::PAIR_SEPARATOR . $member['method_code'];
        }

        return $values;
    }

    public function getTabLabel(): \Magento\Framework\Phrase
    {
        return __('Launchpad Settings');
    }

    public function getTabTitle(): \Magento\Framework\Phrase
    {
        return __('Launchpad Settings');
    }

    public function canShowTab(): bool
    {
        return true;
    }

    public function isHidden(): bool
    {
        return false;
    }
}

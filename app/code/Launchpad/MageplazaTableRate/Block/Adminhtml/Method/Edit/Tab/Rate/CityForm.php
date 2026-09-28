<?php
/*
 * TASK-5XQXZK (DEC-TASK5XQXZK-001) — Mageplaza rate form + optional City/Area field.
 * TASK-JZXM66 — City/Area select upgrade: cascading Region → City/Area select (AJAX options
 * feed), stale stored-code warning, City Reference / Import Template download links.
 *
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Launchpad\MageplazaTableRate\Block\Adminhtml\Method\Edit\Tab\Rate;

use Launchpad\MageplazaTableRate\Model\MethodSettingsProvider;
use Mageplaza\TableRateShipping\Block\Adminhtml\Method\Edit\Tab\Rate\Form as MageplazaRateForm;
use Mageplaza\TableRateShipping\Model\RegistryConstants;
use Magento\Framework\Escaper;

/**
 * Preference subclass: after Mageplaza builds its conditions form, adds the optional
 * `City / Area` constraint field. The posted `city_code` travels inside the same formData
 * payload; Mageplaza's resource layer filters it out of its own tables and the Launchpad
 * ResourceRateSavePlugin persists/removes the constraint row instead.
 *
 * The field is a SELECT (TASK-JZXM66): server-rendered with the wildcard entry plus — on edit
 * — the stored code with its resolved label (or the raw code + warning when it no longer
 * resolves); `view/adminhtml/web/js/city-selector.js` fills the options per selected region
 * through the launchpad_mptablerate/city/options feed. The name stays `city_code`, the
 * persisted identity stays the raw code — labels are display-only. The field is inserted
 * DIRECTLY AFTER `region` (addField $after) — it belongs to the geographic condition group.
 *
 * TASK-RT50KH UX round — the "City / Area Data — Download & Import" note sits right below the
 * field and explains the CSV roundtrip: download the current rates CSV (city_code pre-filled,
 * directly importable), the City Reference code list, and where to upload (Import Rates
 * button on the Shipping Rates tab — the importer validates existence + region consistency
 * and persists atomically).
 */
class CityForm extends MageplazaRateForm
{
    private MethodSettingsProvider $settingsProvider;

    private Escaper $escaper;

    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Data\FormFactory $formFactory,
        \Magento\Directory\Model\Config\Source\Country $country,
        \Magento\Directory\Model\Config\Source\Allregion $allRegion,
        \Mageplaza\TableRateShipping\Helper\Data $helper,
        \Magento\Catalog\Model\Product\Attribute\Repository $repository,
        MethodSettingsProvider $settingsProvider,
        Escaper $escaper,
        array $data = []
    ) {
        $this->settingsProvider = $settingsProvider;
        $this->escaper = $escaper;
        parent::__construct($context, $registry, $formFactory, $country, $allRegion, $helper, $repository, $data);
    }

    protected function _prepareForm()
    {
        parent::_prepareForm();

        $form = $this->getForm();
        $fieldset = $form ? $form->getElement('cond_fieldset') : null;
        if ($fieldset === null || $this->settingsProvider === null) {
            return $this;
        }

        $rateId = (int) ($this->registeredRate()?->getId() ?? 0);
        $cityCode = $rateId > 0
            ? trim((string) ($this->settingsProvider->fetchCityCodes([$rateId])[$rateId] ?? ''))
            : '';

        $values = [['value' => '', 'label' => __(MethodSettingsProvider::WILDCARD_OPTION_LABEL)]];
        $note = __(
            'Pick a City / Area to narrow this rate, or keep "All" to apply it everywhere. '
            . 'The list follows the selected State/Region. Unknown codes are rejected on save.'
        );
        $staleWarning = '';
        if ($cityCode !== '') {
            $label = $this->settingsProvider->cityLabel($cityCode);
            if ($label === null) {
                // Never silently wildcard a stored code — keep the raw code selectable + warn.
                $label = (string) __('%1 (unknown code)', $cityCode);
                $note = __('Stored City/Area code %1 no longer exists — please re-select or clear.', $cityCode);
                $staleWarning = sprintf(
                    '<div class="message message-error error"><span>%s</span></div>',
                    $this->escaper->escapeHtml((string) $note)
                );
            }
            $values[] = ['value' => $cityCode, 'label' => $label];
        }

        // $after 'region' — the City / Area constraint belongs to the geographic condition
        // group (country → region → city), NOT at the end of the fieldset (TASK-RT50KH UX).
        $fieldset->addField('city_code', 'select', [
            'name' => 'city_code',
            'label' => __('City / Area'),
            'title' => __('City / Area'),
            'values' => $values,
            'value' => $cityCode,
            'note' => $note,
            'after_element_html' => $staleWarning . $this->getCitySelectorHtml(),
        ], 'region');

        $fieldset->addField('launchpad_city_reference', 'note', [
            'name' => 'launchpad_city_reference',
            'label' => __('City / Area Data — Download & Import'),
            'title' => __('City / Area Data — Download & Import'),
            'text' => $this->getDownloadLinksHtml(),
        ], 'city_code');

        return $this;
    }

    /**
     * Marker + requireJS bootstrap for the cascading selector. The form HTML is injected into
     * the modal via jQuery `.html()` (Mageplaza rate/buttons.js), so the inline script IS
     * evaluated on every modal render; the JS component initializes each marker exactly once.
     */
    private function getCitySelectorHtml(): string
    {
        return sprintf(
            '<div class="launchpad-city-selector" data-options-url="%s"></div>' .
            '<script>require(["Launchpad_MageplazaTableRate/js/city-selector"], function (initCitySelector) {' .
            'initCitySelector(); });</script>',
            $this->escaper->escapeHtmlAttr($this->getUrl('launchpad_mptablerate/city/options'))
        );
    }

    /**
     * The current rate row from the Mageplaza registry (registered by the RateForm controller
     * before this block is created). The parent's own rate getter is private — reaching it
     * from the subclass scope would fall through to AbstractBlock::__call and always miss.
     */
    private function registeredRate(): ?\Mageplaza\TableRateShipping\Model\Rate
    {
        $rate = $this->_coreRegistry->registry(RegistryConstants::RATE);

        return $rate instanceof \Mageplaza\TableRateShipping\Model\Rate ? $rate : null;
    }

    private function getDownloadLinksHtml(): string
    {
        $method = $this->_coreRegistry->registry(RegistryConstants::METHOD);
        $methodId = $method instanceof \Mageplaza\TableRateShipping\Model\Method ? (int) $method->getId() : 0;

        $links = [];
        // if ($methodId > 0) {
        //     $links[] = [
        //         $this->getUrl('mptablerate/method/rateExportCsv', ['id' => $methodId]),
        //         __('Download: Current Rates CSV — pre-filled, edit the city_code column and import back'),
        //         __('All rates of this method with their current City / Area in the last column.'),
        //     ];
        // }
        $links[] = [
            $this->getUrl('launchpad_mptablerate/city/referenceCsv'),
            __('Download: City Reference CSV — every valid City / Area code'),
            __('The identity for the CSV `city_code` column.'),
        ];
        // $links[] = [
        //     $this->getUrl('launchpad_mptablerate/city/importTemplate'),
        //     __('Download: Import Template — empty CSV with a worked example'),
        //     __('Same columns as the importer expects.'),
        // ];
        // if ($methodId > 0) {
        //     $links[] = [
        //         $this->getUrl('mptablerate/method/importGrid', ['id' => $methodId]),
        //         __('Import: upload the edited CSV (validates + persists City / Area per row)'),
        //         __('Opens the import page of this method; unknown codes are rejected.'),
        //     ];
        // }

        $html = '<div class="launchpad-city-downloads"><p class="note">'
            . (string) __('Update City / Area in bulk: download, edit the city_code column, import back. '
                . 'The import APPENDS new rate rows (native Mageplaza semantics) — review the Rates '
                . 'grid afterwards and remove superseded rows.')
            . '</p>';
        foreach ($links as [$url, $label, $purpose]) {
            $html .= sprintf(
                '<div><a href="%s">%s</a><br/><span class="note">%s</span></div>',
                $this->escaper->escapeHtmlAttr($url),
                $this->escaper->escapeHtml((string) $label),
                $this->escaper->escapeHtml((string) $purpose)
            );
        }
        $html .= '</div>';

        return $html;
    }
}

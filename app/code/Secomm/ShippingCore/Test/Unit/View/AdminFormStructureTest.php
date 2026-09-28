<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Test\Unit\View;

use Magento\Framework\Component\ComponentRegistrar;
use PHPUnit\Framework\TestCase;

/**
 * TASK-WY6WP5 — structural UI-contract tests (directive §14/§34): the Shipping Zone form
 * is GEOGRAPHY ONLY (no carrier reference block, no raw code entry) and the Shipping
 * Coverage form carries the generic target fields with the availability→zones switcher.
 * These read the shipped ui_component XML — the single source of what the admin renders.
 */
class AdminFormStructureTest extends TestCase
{
    private static function modulePath(): string
    {
        $registrar = new ComponentRegistrar();

        return (string) $registrar->getPath(ComponentRegistrar::MODULE, 'Secomm_ShippingCore');
    }

    private static function zoneFormXml(): string
    {
        return (string) file_get_contents(
            self::modulePath() . '/view/adminhtml/ui_component/secomm_shippingcore_zone_form.xml'
        );
    }

    private static function coverageFormXml(): string
    {
        return (string) file_get_contents(
            self::modulePath() . '/view/adminhtml/ui_component/secomm_shippingcore_coverage_form.xml'
        );
    }

    /** Zone form carries no carrier-reference field (geography only). */
    public function testZoneFormHasNoCarrierReferenceBlock(): void
    {
        $xml = self::zoneFormXml();

        $this->assertStringNotContainsString('assigned_carriers', $xml);
        $this->assertStringNotContainsString('Carriers Referencing This Zone', $xml);
        $this->assertStringNotContainsString('CarrierOptions', $xml);
    }

    /** Zone form geography fields are present and selector-backed (no raw code entry). */
    public function testZoneFormGeographyFieldsAreSelectorBacked(): void
    {
        $xml = self::zoneFormXml();

        foreach (['code', 'label', 'country', 'enabled', 'include_province_codes', 'include_ward_codes'] as $field) {
            $this->assertStringContainsString(sprintf('name="%s"', $field), $xml);
        }
        // No raw province/ward code textareas — province/ward go through the selectors.
        $this->assertStringNotContainsString('textarea', $xml);
        // Province field: core ui-select (recipe new_category_form — component attribute).
        $this->assertStringContainsString('Magento_Ui/js/form/element/ui-select', $xml);
        // Ward field: the cascading subclass keeps the AJAX reload behavior.
        $this->assertStringContainsString('Secomm_ShippingCore/js/form/element/ward-select', $xml);
        $this->assertStringContainsString('provincesValue', $xml);
    }

    /** Coverage form: generic target fields + availability→zones switcher. */
    public function testCoverageFormHasTargetAbstractionAndSwitcher(): void
    {
        $xml = self::coverageFormXml();

        foreach (['target_type', 'target_code', 'is_create', 'applies_to', 'destination_scope', 'allowed_zone_codes', 'rate_source_mode', 'address_resolution_policy'] as $field) {
            $this->assertStringContainsString(sprintf('name="%s"', $field), $xml);
        }
        // Availability = ALL hides the zones field (directive §10, core switcherConfig).
        $this->assertStringContainsString('switcherConfig', $xml);
        $this->assertStringContainsString('<callback>hide</callback>', $xml);
        $this->assertStringContainsString('<callback>show</callback>', $xml);
        // Zones selector: searchable core ui-select fed by ReferencableZoneCodes.
        $this->assertStringContainsString('ui/grid/filters/elements/ui-select', $xml);
        $this->assertStringContainsString('ReferencableZoneCodes', $xml);
        // Carrier options for the create flow come from the registry minus configured.
        $this->assertStringContainsString('RegisteredCarrierOptions', $xml);
    }

    /** The removed custom component is gone from disk — no dead code surface. */
    public function testLegacySearchableMultiselectComponentIsDeleted(): void
    {
        $js = self::modulePath() . '/view/adminhtml/web/js/form/element/searchable-multiselect.js';
        $tmpl = self::modulePath() . '/view/adminhtml/web/templates/form/element/searchable-multiselect.html';

        $this->assertFileDoesNotExist($js);
        $this->assertFileDoesNotExist($tmpl);
        $this->assertFileExists(self::modulePath() . '/view/adminhtml/web/js/form/element/ward-select.js');
    }
}

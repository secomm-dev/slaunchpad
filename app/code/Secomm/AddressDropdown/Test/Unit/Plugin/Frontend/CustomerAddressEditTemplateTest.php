<?php
declare(strict_types=1);

namespace Secomm\AddressDropdown\Test\Unit\Plugin\Frontend;

use Magento\Customer\Block\Address\Edit;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\AddressDropdown\Helper\Data;
use Secomm\AddressDropdown\Model\OptionSource\RendererMode;
use Secomm\AddressDropdown\Plugin\Frontend\CustomerAddressEditTemplate;

class CustomerAddressEditTemplateTest extends TestCase
{
    private Data&MockObject $helper;
    private CustomerAddressEditTemplate $plugin;

    protected function setUp(): void
    {
        $this->helper = $this->createMock(Data::class);
        $this->plugin = new CustomerAddressEditTemplate($this->helper);
    }

    private function after(string $layoutTemplate): string
    {
        $block = $this->createMock(Edit::class);

        return $this->plugin->afterGetTemplate($block, $layoutTemplate);
    }

    public function testModuleDisabledKeepsLayoutTemplate(): void
    {
        $this->helper->method('isAddressDropdownModuleEnabled')->willReturn(false);
        $this->helper->expects($this->never())->method('getConfigValue');

        $this->assertSame(
            'Secomm_AddressDropdown::hyva/address/edit.phtml',
            $this->after('Secomm_AddressDropdown::hyva/address/edit.phtml')
        );
    }

    public function testLegacyModeKeepsLayoutTemplate(): void
    {
        $this->helper->method('isAddressDropdownModuleEnabled')->willReturn(true);
        $this->helper->method('getConfigValue')->with('address/general/renderer')->willReturn(null);

        $this->assertSame(
            'Secomm_AddressDropdown::hyva/address/edit.phtml',
            $this->after('Secomm_AddressDropdown::hyva/address/edit.phtml')
        );
    }

    public function testSchemaModeSwapsTemplate(): void
    {
        $this->helper->method('isAddressDropdownModuleEnabled')->willReturn(true);
        $this->helper->method('getConfigValue')->with('address/general/renderer')
            ->willReturn(RendererMode::MODE_SCHEMA);

        $this->assertSame(
            'Secomm_AddressDropdown::hyva/address/schema-edit.phtml',
            $this->after('Secomm_AddressDropdown::hyva/address/edit.phtml')
        );
    }

    /**
     * Theme gate (audit 2026-08-28): the Luma template (set by `customer_address_form`
     * handle) must never be swapped — enabling flag `schema` on a Luma store keeps the
     * legacy Luma form rendering.
     */
    public function testSchemaModeKeepsLumaTemplate(): void
    {
        $this->helper->method('isAddressDropdownModuleEnabled')->willReturn(true);
        $this->helper->method('getConfigValue')->with('address/general/renderer')
            ->willReturn(RendererMode::MODE_SCHEMA);

        $this->assertSame(
            'Secomm_AddressDropdown::address/edit.phtml',
            $this->after('Secomm_AddressDropdown::address/edit.phtml')
        );
    }

    /**
     * A child theme overriding the legacy Hyva template opts out of the auto-swap.
     */
    public function testSchemaModeKeepsOverriddenTemplate(): void
    {
        $this->helper->method('isAddressDropdownModuleEnabled')->willReturn(true);
        $this->helper->method('getConfigValue')->with('address/general/renderer')
            ->willReturn(RendererMode::MODE_SCHEMA);

        $this->assertSame(
            'Secomm_Launchpad::Magento_Customer/address/schema-edit.phtml',
            $this->after('Secomm_Launchpad::Magento_Customer/address/schema-edit.phtml')
        );
    }
}

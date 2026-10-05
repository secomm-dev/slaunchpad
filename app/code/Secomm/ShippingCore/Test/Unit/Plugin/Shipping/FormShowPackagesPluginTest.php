<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Test\Unit\Plugin\Shipping;

use Magento\Sales\Model\Order\Shipment;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\ShippingCore\Model\Physical\ShipmentPhysicalPersister;
use Secomm\ShippingCore\Model\Shipment\FulfillmentMetadataPersister;
use Secomm\ShippingCore\Plugin\Shipping\FormShowPackagesPlugin;
use Magento\Shipping\Block\Adminhtml\View\Form;

/**
 * BUG-DT0C4W — the "Show Packages" button is hidden when the shipment carries no NATIVE
 * package entries (the modal it opens reads marker-stripped data and would be empty);
 * native / mixed shipments keep the button untouched.
 */
class FormShowPackagesPluginTest extends TestCase
{
    private Form&MockObject $form;

    private FormShowPackagesPlugin $plugin;

    protected function setUp(): void
    {
        $this->form = $this->createMock(Form::class);
        $this->plugin = new FormShowPackagesPlugin();
    }

    public function testButtonHiddenWhenOnlySecommMarkersExist(): void
    {
        $this->givenPackages([
            ShipmentPhysicalPersister::PACKAGES_KEY => [[48600, 300, 300, 300]],
            FulfillmentMetadataPersister::PACKAGES_KEY => [FulfillmentMetadataPersister::MODE => 'OFFLINE'],
        ]);

        self::assertSame('', $this->plugin->afterGetShowPackagesButton($this->form, '<button>Show</button>'));
    }

    public function testButtonHiddenWhenPackagesEmpty(): void
    {
        $this->givenPackages([]);

        self::assertSame('', $this->plugin->afterGetShowPackagesButton($this->form, '<button>Show</button>'));
    }

    public function testButtonHiddenWhenPackagesNotAnArray(): void
    {
        $this->givenPackages(null);

        self::assertSame('', $this->plugin->afterGetShowPackagesButton($this->form, '<button>Show</button>'));
    }

    public function testButtonKeptWhenNativePackagesExist(): void
    {
        $native = [1 => ['params' => ['weight' => 5, 'length' => 30], 'items' => []]];
        $this->givenPackages($native + [
            ShipmentPhysicalPersister::PACKAGES_KEY => [[48600, 30, 20, 10]],
        ]);

        self::assertSame(
            '<button>Show</button>',
            $this->plugin->afterGetShowPackagesButton($this->form, '<button>Show</button>')
        );
    }

    public function testMissingShipmentKeepsTheResult(): void
    {
        $this->form->method('getShipment')->willReturn(null);

        self::assertSame(
            '<button>Show</button>',
            $this->plugin->afterGetShowPackagesButton($this->form, '<button>Show</button>')
        );
    }

    private function givenPackages(mixed $packages): void
    {
        $shipment = $this->createMock(Shipment::class);
        $shipment->method('getPackages')->willReturn($packages);
        $this->form->method('getShipment')->willReturn($shipment);
    }
}

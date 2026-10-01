<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Plugin\Shipping;

use Magento\Shipping\Block\Adminhtml\View\Form;
use Secomm\ShippingCore\Plugin\Shipping\PackagingBlockPlugin;

/**
 * BUG-DT0C4W — hide the admin "Show Packages" button when the shipment has NO native package
 * entries. The core template gates the button on the RAW `$shipment->getPackages()` column
 * (view/form.phtml:123), which is always truthy for this project's shipments: the Secomm
 * markers (`secomm_physical`, `secomm_fulfillment`) live there. With only markers, the modal
 * the button opens is guaranteed empty (its data is the marker-stripped `Packaging::getPackages()`
 * — BUG-74VGQX + DEC-TASKS52DGA-001), so the button would offer nothing but Print/Cancel.
 *
 * Display-side only: packages data and the modal itself are untouched; shipments carrying real
 * native packages (label-flow carriers) keep the button and modal as before. The confirmed
 * Secomm package facts remain visible on the same page via the "GHN Shipment" section
 * (Secomm_Ghn ProviderStatus) and the "Fulfillment" section (ShippingCore).
 */
class FormShowPackagesPlugin
{
    /**
     * @param Form $subject
     * @param string $result the rendered "Show Packages" button HTML
     * @return string '' when the shipment carries no NATIVE packages (markers only / none)
     */
    public function afterGetShowPackagesButton(Form $subject, string $result): string
    {
        $shipment = $subject->getShipment();
        if ($shipment === null) {
            return $result;
        }

        $packages = $shipment->getPackages();
        if (!is_array($packages) || PackagingBlockPlugin::stripMarkers($packages) === []) {
            return '';
        }

        return $result;
    }
}

<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Plugin\Shipping;

use Magento\Shipping\Block\Adminhtml\Order\Packaging;
use Secomm\ShippingCore\Model\Physical\ShipmentPhysicalPersister;
use Secomm\ShippingCore\Model\Shipment\FulfillmentMetadataPersister;

/**
 * BUG-74VGQX + TASK-S52DGA — read-side guard for the Secomm markers on `sales_shipment.packages`.
 *
 * ShippingCore stores its state under self-identifying string keys (`secomm_physical` — the
 * physical-facts snapshot, DEC-TASK9Q5ZAK-001; `secomm_fulfillment` — the fulfillment-mode
 * metadata, DEC-TASKS52DGA-001) — entries without `params` that the native admin packaging
 * surfaces (`packed.phtml` and the printPackage PDF, both via Packaging::getPackages()) render
 * unguarded and fatally. This plugin strips the markers from the block's display data only:
 * the persisters' `read()` methods consume the shipment model directly, so the records stay
 * intact for the carrier retry/replay flows and the admin fulfillment surfaces.
 */
class PackagingBlockPlugin
{
    /** Marker keys stored by ShippingCore on the packages column (strip list). */
    private const MARKER_KEYS = [
        ShipmentPhysicalPersister::PACKAGES_KEY,
        FulfillmentMetadataPersister::PACKAGES_KEY,
    ];

    /**
     * Strip the Secomm markers from the admin packaging display data.
     *
     * @param Packaging $subject
     * @param mixed $result
     * @return mixed non-array results pass through untouched; otherwise the array without the marker keys
     */
    public function afterGetPackages(Packaging $subject, $result)
    {
        if (!is_array($result)) {
            return $result;
        }

        return self::stripMarkers($result);
    }

    /**
     * BUG-DT0C4W — shared marker strip for display-side consumers of the raw packages column
     * (the packaging modal here, and the "Show Packages" button visibility in
     * FormShowPackagesPlugin). Native (numeric-keyed) entries are always preserved.
     *
     * @param array $packages raw `$shipment->getPackages()` data
     * @return array the same array without the Secomm marker keys
     */
    public static function stripMarkers(array $packages): array
    {
        foreach (self::MARKER_KEYS as $markerKey) {
            unset($packages[$markerKey]);
        }

        return $packages;
    }
}

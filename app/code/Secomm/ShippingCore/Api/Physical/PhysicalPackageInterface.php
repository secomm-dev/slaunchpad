<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Api\Physical;

/**
 * DEC-TASK9Q5ZAK-001 — ONE real physical package/parcel handed to a carrier after warehouse
 * packing. NOT a Magento product, NOT a carrier item/line — carriers interpret packages into
 * their own payload shapes (GHN heavy items[], GHTK products[], VTP root dims) at their adapter
 * boundary. Units are normalized here: grams / centimetres, integers (no float ambiguity).
 */
interface PhysicalPackageInterface
{
    public function getWeightG(): int;

    public function getLengthCm(): int;

    public function getWidthCm(): int;

    public function getHeightCm(): int;
}

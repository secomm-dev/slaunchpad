<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Model;

use Magento\Framework\Model\AbstractModel;
use Secomm\ShippingCore\Model\ResourceModel\CarrierTrackingState as ResourceModel;

/**
 * Carrier tracking state row (SL-017) — see db_schema.xml. One row per
 * (carrier_code, tracking_number); normalized + raw carrier status kept side
 * by side for debugging/reconciliation.
 *
 * @method CarrierTrackingState setCarrierCode(string $code)
 * @method string getCarrierCode()
 * @method CarrierTrackingState setTrackingNumber(string $number)
 * @method string getTrackingNumber()
 * @method CarrierTrackingState setShipmentEntityId(?int $id)
 * @method int|null getShipmentEntityId()
 * @method CarrierTrackingState setNormalizedStatus(string $status)
 * @method string getNormalizedStatus()
 * @method CarrierTrackingState setCarrierStatusCode(?string $code)
 * @method string|null getCarrierStatusCode()
 * @method CarrierTrackingState setCarrierStatusMessage(?string $message)
 * @method string|null getCarrierStatusMessage()
 * @method CarrierTrackingState setCarrierStatusUpdatedAt(?string $time)
 * @method string|null getCarrierStatusUpdatedAt()
 * @method CarrierTrackingState setLastSyncedAt(?string $time)
 * @method string|null getLastSyncedAt()
 * @method CarrierTrackingState setSource(?string $source)
 * @method string|null getSource()
 */
class CarrierTrackingState extends AbstractModel
{
    protected function _construct(): void
    {
        $this->_init(ResourceModel::class);
    }
}

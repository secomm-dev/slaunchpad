<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\FulfillmentCore\Api;

/**
 * Push status and origin constants for secomm_fulfillment_export rows.
 */
final class ExportPushStatus
{
    public const PENDING = 'pending';
    public const SUCCESS = 'success';
    public const FAILED = 'failed';

    /** Magento-origin outbound export mapping (never create for remote-only orders). */
    public const ORIGIN_MAGENTO = 'magento';

    private function __construct()
    {
    }
}

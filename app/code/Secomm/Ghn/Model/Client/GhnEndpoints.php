<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Model\Client;

/**
 * GHN endpoint paths (relative to the environment base URL). Path inventory rule (SPIKE-9Z231Q):
 * only endpoints verified from the running legacy integration or official docs are declared here —
 * request/response FIELD shapes are never invented.
 *
 * Sources (verified 2026-09-10):
 *  - New model (GHN_ADMIN_2025): developer.ghn.vn "Get Province (New)" /
 *    "Get Ward (New)" — GET, params offset/limit (≤200) resp. province_id + paging;
 *    data[] fields `_id, name, extension_names, type, parent_id, status`
 *    (1=active, 2=disabled, 10=deleted); `name` verbatim = to_province_name / to_ward_name
 *    with is_new_to_address=true.
 *  - Legacy model (GHN_ADMIN_PRE_2025): developer.ghn.vn "Get Province" + legacy
 *    Secomm_GiaoHangNhanh config/CLI — GET master-data/{province,district,ward};
 *    fields ProvinceID/ProvinceName, DistrictID/DistrictName, WardCode/WardName.
 */
final class GhnEndpoints
{
    public const AVAILABLE_SERVICES = 'v2/shipping-order/available-services';
    public const CALCULATE_FEE = 'v2/shipping-order/fee';
    public const CREATE_ORDER = 'v2/shipping-order/create';
    public const ORDER_INFO = 'v2/shipping-order/detail';
    public const CANCEL_ORDER = 'v2/switch-status/cancel';
    /** TASK-PWHG0V doc-correction: current official GHN Print Order contract (developer.ghn.vn
     *  docs, fetched 2026-09-16) — token generation; the label itself renders on provider-hosted
     *  print URLs (`/a5/public-api/printA5|print80x80|print52x70?token=…`). The former
     *  `v2/shipping-order/print` path is dead (sandbox 404) and was never the documented flow.
     *  No consumer yet — future GHN Label Adapter follow-up. */
    public const GEN_PRINT_TOKEN = 'v2/a5/gen-token';
    public const RETURN_ORDER = 'v2/switch-status/return';
    public const LEADTIME = 'v2/shipping-order/leadtime';

    /** Legacy (pre-2025) administrative master data — GHN_ADMIN_PRE_2025 sync. */
    public const MASTER_DATA_PROVINCES = 'master-data/province';
    public const MASTER_DATA_DISTRICTS = 'master-data/district';
    public const MASTER_DATA_WARDS = 'master-data/ward';

    /** Current (2025) administrative master data — GHN_ADMIN_2025 sync. */
    public const MASTER_DATA_PROVINCES_V3 = 'v3/master-data/province/all';
    public const MASTER_DATA_WARDS_BY_PROVINCE_V3 = 'v3/master-data/ward/all-by-province-id';

    /** Max page size accepted by the v3 new-model master-data endpoints. */
    public const V3_PAGE_SIZE = 200;

    private function __construct()
    {
    }
}

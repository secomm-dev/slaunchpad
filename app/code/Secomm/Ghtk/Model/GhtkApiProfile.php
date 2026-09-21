<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Secomm\Ghtk\Model;

use Secomm\ShippingCore\Api\CarrierApiProfileInterface;
use Secomm\VietNamAddress\Model\Scheme\VnSchemes;

/**
 * TASK-7AJ3K8 r1 (DEC-FEATYA2C0W-004 D3 + DEC-TASK7AJ3K8-002) — the GHTK API profile: a
 * COMPLETE carrier-owned capability bundle, not isolated flags. `GHTK_2025` speaks the
 * current (post-2025 merge) VN administrative names as `TEXT_NATIVE` (canonical name_vi,
 * no carrier administrative ids) over the services.giaohangtietkiem.vn shipment API.
 * Switching to a future GHTK generation means a new profile class + a carrier-owned config
 * read — never partial flag flips. No mapping repository/business logic lives here.
 */
final class GhtkApiProfile implements CarrierApiProfileInterface
{
    public const CODE = 'GHTK_2025';
    public const ADDRESS_MODE_TEXT_NATIVE = 'TEXT_NATIVE';

    private const FEE_PATH = '/services/shipment/fee';
    private const ORDER_PATH = '/services/shipment/order';
    private const ORDER_STATUS_PATH = '/services/shipment/v2/';
    private const CANCEL_PATH = '/services/shipment/cancel/';
    private const PICKUP_LIST_PATH = '/services/shipment/list_pick_add';

    public function getCode(): string
    {
        return self::CODE;
    }

    public function getAddressScheme(): ?string
    {
        return VnSchemes::VN_ADMIN_2025;
    }

    /** The carrier address representation mode — canonical Vietnamese text, no carrier ids. */
    public function getAddressMode(): string
    {
        return self::ADDRESS_MODE_TEXT_NATIVE;
    }

    public function getFeePath(): string
    {
        return self::FEE_PATH;
    }

    public function getOrderPath(): string
    {
        return self::ORDER_PATH;
    }

    public function getOrderStatusPath(string $labelId): string
    {
        return self::ORDER_STATUS_PATH . rawurlencode($labelId);
    }

    /** Cancel endpoint prefix — caller appends the rawurlencoded identifier. */
    public function getCancelPath(): string
    {
        return self::CANCEL_PATH;
    }

    /** Merchant pickup/warehouse list endpoint (TASK-3HPB76 — admin tooling only). */
    public function getPickupListPath(): string
    {
        return self::PICKUP_LIST_PATH;
    }
}

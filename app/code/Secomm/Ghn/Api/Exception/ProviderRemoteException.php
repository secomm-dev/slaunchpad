<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Api\Exception;

/**
 * SPEC-FEAT-FQWEQ3 §11 — unmapped GHN failure. This is also the false-success guard: GHN can
 * answer HTTP 200 with an EMPTY body (shop-not-found quirk, SPIKE-9Z231Q R11) — a naive client
 * would treat that as success. The client must throw this exception instead.
 */
class ProviderRemoteException extends GhnApiException
{
}

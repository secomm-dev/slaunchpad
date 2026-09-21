<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Api\Exception;

/**
 * SPEC-FEAT-FQWEQ3 §11 — connection or request timeout (carriers/secomm_ghn/connection_timeout /
 * request_timeout exceeded). Legacy integration never set any timeout — the new client makes it
 * impossible to hang a checkout request on GHN.
 */
class ProviderTimeoutException extends GhnApiException
{
}

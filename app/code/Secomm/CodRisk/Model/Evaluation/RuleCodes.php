<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\CodRisk\Model\Evaluation;

/**
 * Rule codes + P1 business precedence (sortOrder lives in di.xml — AD-03/AD-04:
 * precedence is code-controlled, never an admin runtime setting).
 */
class RuleCodes
{
    public const BLACKLIST = 'blacklist';
    public const SPAM_ORDER = 'spam_order';
    public const ALLOWLIST = 'allowlist';
    public const HISTORICAL = 'historical';

    public const REASON_BLACKLISTED = 'blacklisted';
    public const REASON_SPAM_MATCHED = 'spam_order_matched';
    public const REASON_HISTORICAL_WARNING = 'historical_warning';
    public const REASON_HISTORICAL_BLOCK = 'historical_block';
}

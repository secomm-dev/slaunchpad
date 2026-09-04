<?php
declare(strict_types=1);
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\VietNamAddress\Model\Data;

use Secomm\VietNamAddress\Api\Data\VnOperationalIdentityInterface;
use Secomm\VietNamAddress\Api\Data\VnOperationalResolutionInterface;

/**
 * TASK-Q4B98P — immutable bridge outcome (pattern: VnAddressResolutionData).
 */
class VnOperationalResolutionData implements VnOperationalResolutionInterface
{
    public function __construct(
        private readonly ?VnOperationalIdentityInterface $identity,
        private readonly ?string $reason
    ) {
    }

    public static function resolved(VnOperationalIdentityInterface $identity): self
    {
        return new self($identity, null);
    }

    public static function unresolved(string $reason): self
    {
        return new self(null, $reason);
    }

    public function isResolved(): bool
    {
        return $this->identity !== null;
    }

    public function getIdentity(): ?VnOperationalIdentityInterface
    {
        return $this->identity;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }
}

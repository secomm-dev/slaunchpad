<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\CodRisk\Model\Evaluation;

use Secomm\CodRisk\Api\CodRiskRuleInterface;

/**
 * Ordered rule pool. The order comes from di.xml array sortOrder — business
 * precedence is code-controlled (AD-04), not an admin runtime setting.
 */
class RulePool
{
    /**
     * @param CodRiskRuleInterface[] $rules Sorted by di.xml sortOrder (10/20/30/40).
     */
    public function __construct(
        private readonly array $rules = [],
    ) {
    }

    /**
     * @return CodRiskRuleInterface[]
     */
    public function getRules(): array
    {
        return array_values($this->rules);
    }
}

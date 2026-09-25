<?php
declare(strict_types=1);

namespace Tiktok\Tiktok\Model\TrustSignals\PartnerData;

/**
 * Define tier list based on input param
 */
abstract class AbstractBudgetIndicator
{
    /**
     * Provide range of tiers
     *
     * @return array|int[]
     */
    public function range(): array
    {
        return [];
    }

    /**
     * Retrieve tier info
     *
     * @param int $value
     * @return string|null
     */
    public function getTier(int $value): ?string
    {
        $range = $this->range();
        if (!$range) {
            return null;
        }

        foreach ($this->range() as $tier => [$min, $max]) {
            if ($value >= $min && $value <= $max) {
                return $tier;
            }
        }

        return null;
    }
}

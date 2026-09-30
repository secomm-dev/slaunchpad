<?php
declare(strict_types=1);

namespace Tiktok\Tiktok\Model\TrustSignals\PartnerData;

/**
 * @inheritdoc
 */
class L30Orders extends AbstractBudgetIndicator
{
    /**
     * @inheritdoc
     */
    public function range(): array
    {
        return [
            'TIER_1' => [0, 26],
            'TIER_2' => [26, 50],
            'TIER_3' => [51, 200],
            'TIER_4' => [201, 500],
            'TIER_5' => [501, 1000],
            'TIER_6' => [1001, 5000],
            'TIER_7' => [5001, 10000],
            'TIER_8' => [10001, 25000],
            'TIER_9' => [26000, 50000],
            'TIER_10' => [50000, PHP_INT_MAX]
        ];
    }
}

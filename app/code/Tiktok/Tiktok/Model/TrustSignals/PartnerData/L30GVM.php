<?php
declare(strict_types=1);

namespace Tiktok\Tiktok\Model\TrustSignals\PartnerData;

/**
 * @inheritdoc
 */
class L30GVM extends AbstractBudgetIndicator
{
    /**
     * @inheritdoc
     */
    public function range(): array
    {
        return [
            'TIER_1' => [0, 1000],
            'TIER_2' => [1001, 5000],
            'TIER_3' => [5001, 10000],
            'TIER_4' => [10001, 15000],
            'TIER_5' => [15001, 20000],
            'TIER_6' => [20001, 50000],
            'TIER_7' => [50001, 100000],
            'TIER_8' => [100001, 200000],
            'TIER_9' => [200001, 300000],
            'TIER_10' => [300001, PHP_INT_MAX]
        ];
    }
}

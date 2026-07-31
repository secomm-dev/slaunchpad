<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\ZaloPay\Plugin\Model\Order;

use Magento\Sales\Model\Order\Creditmemo;

/**
 * Plugin to add PROCESSING state to Creditmemo states
 */
class CreditmemoPlugin
{
    /**
     * Processing state constant
     */
    public const STATE_PROCESSING = 4;

    /**
     * Around plugin to add PROCESSING state to states array
     *
     * @param Creditmemo $subject
     * @param callable $proceed
     * @return array
     */
    public function aroundGetStates(Creditmemo $subject, callable $proceed): array
    {
        $states = $proceed();

        // Add PROCESSING state if not already present
        if (!isset($states[self::STATE_PROCESSING])) {
            $states[self::STATE_PROCESSING] = __('Processing');
        }

        return $states;
    }
}

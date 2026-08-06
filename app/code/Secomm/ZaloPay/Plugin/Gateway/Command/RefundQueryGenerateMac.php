<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\ZaloPay\Plugin\Gateway\Command;

use Secomm\ZaloPay\Gateway\Helper\Authorization;
use Secomm\ZaloPay\Gateway\Request\AbstractDataBuilder;
use Magento\Payment\Gateway\Request\BuilderComposite;

/**
 * Class RefundGenerateMac
 *
 * @see BuilderComposite
 */
class RefundQueryGenerateMac
{
    /**
     * PayUrlGenerateMac constructor.
     *
     * @param Authorization $authorization
     */
    public function __construct(
        private readonly Authorization $authorization
    ) {
    }

    /**
     * Generate Mac
     *
     * @param BuilderComposite $subject
     * @param $result
     * @return mixed
     */
    public function afterBuildRequestData($subject, $result): mixed
    {
        if (is_array($result)) {
            $newParams = [];
            foreach ($this->getMacKeys() as $key) {
                if (!empty($result[$key])) {
                    $newParams[] = $result[$key];
                }
            }
            $result[AbstractDataBuilder::MAC] = $this->authorization->getMac($newParams);
        }

        return $result;
    }

    /**
     * @return array
     */
    protected function getMacKeys(): array
    {
        return [
            AbstractDataBuilder::APP_ID,
            AbstractDataBuilder::M_REFUND_ID,
            AbstractDataBuilder::TIMESTAMP
        ];
    }
}

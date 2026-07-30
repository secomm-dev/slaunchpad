<?php
/************************************************************
 * *
 *  * Copyright © Secomm. All rights reserved.
 *  * See COPYING.txt for license details.
 *  *
 *  * @author    Secomm Teams
 * *  @project   Layered Navigation
 */

namespace Secomm\ZaloPay\Plugin\Gateway\Command;

use Secomm\ZaloPay\Gateway\Helper\Authorization;
use Secomm\ZaloPay\Gateway\Request\AbstractDataBuilder;
use Magento\Payment\Gateway\Request\BuilderComposite;

class PayUrlGenerateMac
{
    /**
     * @var Authorization
     */
    private Authorization $authorization;

    /**
     * PayUrlGenerateMac constructor.
     *
     * @param Authorization $authorization
     */
    public function __construct(
        Authorization $authorization
    ) {
        $this->authorization = $authorization;
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
            AbstractDataBuilder::APP_TRANS_ID,
            AbstractDataBuilder::APP_USER,
            AbstractDataBuilder::AMOUNT,
            AbstractDataBuilder::APP_TIME,
            AbstractDataBuilder::EMBED_DATA,
            AbstractDataBuilder::ITEM
        ];
    }
}

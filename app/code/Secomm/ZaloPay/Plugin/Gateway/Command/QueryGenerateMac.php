<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\ZaloPay\Plugin\Gateway\Command;

use Secomm\ZaloPay\Gateway\Helper\Authorization;
use Secomm\ZaloPay\Gateway\Request\AbstractDataBuilder;
use Magento\Payment\Gateway\Request\BuilderComposite;

class QueryGenerateMac
{
    /**
     * QueryGenerateMac constructor.
     *
     * @param Authorization $authorization
     */
    public function __construct(
        private readonly Authorization $authorization
    ) {
    }

    /**
     * Generate the v2/query MAC over "app_id|app_trans_id|key1" and strip the
     * plaintext key from the outbound payload.
     *
     * @param BuilderComposite $subject
     * @param mixed $result
     * @return mixed
     */
    public function afterBuildRequestData($subject, $result): mixed
    {
        if (is_array($result)
            && isset($result[AbstractDataBuilder::APP_ID], $result[AbstractDataBuilder::APP_TRANS_ID])
            && !empty($result[AbstractDataBuilder::KEY_1])
        ) {
            $result[AbstractDataBuilder::MAC] = $this->authorization->getMac([
                $result[AbstractDataBuilder::APP_ID],
                $result[AbstractDataBuilder::APP_TRANS_ID],
                $result[AbstractDataBuilder::KEY_1],
            ]);
            unset($result[AbstractDataBuilder::KEY_1]);
        }

        return $result;
    }
}

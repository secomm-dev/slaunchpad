<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\ZaloPay\Gateway\Request;

use Secomm\ZaloPay\Gateway\Helper\Authorization;
use Magento\Payment\Gateway\ConfigInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;

/**
 * Builds the v2/query (transaction status) request body.
 *
 * Per ZaloPay spec the body carries app_id, app_trans_id and mac, where
 * mac = HMAC-SHA256(key1, app_id + "|" + app_trans_id + "|" + key1). The
 * key1 value is emitted here only so the QueryGenerateMac plugin can fold
 * it into the MAC — the plugin strips it from the payload before transfer.
 */
class QueryTransactionDataBuilder extends AbstractDataBuilder implements BuilderInterface
{
    /**
     * QueryTransactionDataBuilder constructor.
     *
     * @param ConfigInterface $config
     * @param Authorization $authorization
     */
    public function __construct(
        private readonly ConfigInterface $config,
        private readonly Authorization   $authorization
    ) {
    }

    /**
     * @param array $buildSubject
     * @return array
     */
    public function build(array $buildSubject): array
    {
        $appTransId = (string)($buildSubject[self::APP_TRANS_ID] ?? '');
        if ($appTransId === '') {
            throw new \InvalidArgumentException('app_trans_id should be provided');
        }

        return [
            self::APP_ID => $this->config->getValue(self::APP_ID),
            self::APP_TRANS_ID => $appTransId,
            self::KEY_1 => $this->authorization->getKey1(),
        ];
    }
}

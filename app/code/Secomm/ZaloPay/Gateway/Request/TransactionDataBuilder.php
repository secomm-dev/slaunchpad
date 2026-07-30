<?php
/************************************************************
 * *
 *  * Copyright © Secomm. All rights reserved.
 *  * See COPYING.txt for license details.
 *  *
 *  * @author    Secomm Teams
 * *  @project   ZaloPay
 */

namespace Secomm\ZaloPay\Gateway\Request;

use Magento\Payment\Gateway\Request\BuilderInterface;

class TransactionDataBuilder extends AbstractDataBuilder implements BuilderInterface
{
    /**
     * Method
     */
    const METHOD = 'method';

    /**
     * @var string
     */
    private $requestType;

    /**
     * TransactionDataBuilder constructor.
     *
     * @param $requestType
     */
    public function __construct(
        $requestType
    ) {
        $this->requestType = $requestType;
    }

    /**
     * @inheritdoc
     */
    public function build(array $buildSubject): array
    {
        return [];
    }
}

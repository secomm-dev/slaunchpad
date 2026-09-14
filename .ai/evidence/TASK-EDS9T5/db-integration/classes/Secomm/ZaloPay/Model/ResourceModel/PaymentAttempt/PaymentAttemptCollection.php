<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\ZaloPay\Model\ResourceModel\PaymentAttempt;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Secomm\ZaloPay\Model\PaymentAttempt;
use Secomm\ZaloPay\Model\ResourceModel\PaymentAttemptResource;

class PaymentAttemptCollection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_eventPrefix = 'secomm_zalopay_payment_attempt_collection';

    /**
     * Initialize collection model.
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(PaymentAttempt::class, PaymentAttemptResource::class);
    }
}

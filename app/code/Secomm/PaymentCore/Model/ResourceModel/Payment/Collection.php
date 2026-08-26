<?php
/**
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\PaymentCore\Model\ResourceModel\Payment;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Secomm\PaymentCore\Model\Payment as PaymentModel;
use Secomm\PaymentCore\Model\ResourceModel\Payment as PaymentResource;

/**
 * FEAT-CSWYEJ — collection for secomm_paymentcore_payment.
 */
class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = 'entity_id';

    /**
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(PaymentModel::class, PaymentResource::class);
    }
}

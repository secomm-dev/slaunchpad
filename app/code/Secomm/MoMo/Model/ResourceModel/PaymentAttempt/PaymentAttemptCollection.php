<?php
/**
 * Collection of MoMo payment attempts.
 *
 * @author    Secomm Teams
 * @copyright Copyright (c) 2026 Secomm (https://www.secomm.vn)
 * @package   Secomm_MoMo
 */
declare(strict_types=1);

namespace Secomm\MoMo\Model\ResourceModel\PaymentAttempt;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Secomm\MoMo\Model\PaymentAttempt;
use Secomm\MoMo\Model\ResourceModel\PaymentAttemptResource;

class PaymentAttemptCollection extends AbstractCollection
{
    /**
     * @inheritdoc
     */
    protected $_idFieldName = 'entity_id';

    /**
     * @inheritdoc
     */
    protected function _construct(): void
    {
        $this->_init(PaymentAttempt::class, PaymentAttemptResource::class);
    }
}

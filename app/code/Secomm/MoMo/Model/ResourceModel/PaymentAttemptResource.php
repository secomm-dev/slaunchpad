<?php
/**
 * Resource model for MoMo payment attempts.
 *
 * @author    Secomm Teams
 * @copyright Copyright (c) 2026 Secomm (https://www.secomm.vn)
 * @package   Secomm_MoMo
 */
declare(strict_types=1);

namespace Secomm\MoMo\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class PaymentAttemptResource extends AbstractDb
{
    /** Attempt table name. */
    public const TABLE = 'secomm_momo_payment_attempt';

    /**
     * @inheritdoc
     */
    protected function _construct(): void
    {
        $this->_init(self::TABLE, 'entity_id');
    }
}

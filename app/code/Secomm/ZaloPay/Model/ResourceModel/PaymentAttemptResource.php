<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\ZaloPay\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Secomm\ZaloPay\Api\Data\PaymentAttemptInterface;

class PaymentAttemptResource extends AbstractDb
{
    /**
     * @var string
     */
    protected $_eventPrefix = 'secomm_zalopay_payment_attempt_resource_model';

    /**
     * Initialize resource model.
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init('secomm_zalopay_payment_attempt', PaymentAttemptInterface::ENTITY_ID);
        $this->_useIsObjectNew = true;
    }

    /**
     * Fetch the raw attempt row with SELECT ... FOR UPDATE.
     *
     * The caller must hold an open DB transaction; the row lock is released
     * on commit/rollback.
     *
     * @param string $appTransId
     * @return array|null
     */
    public function lockRowByAppTransId(string $appTransId): ?array
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getMainTable())
            ->where(PaymentAttemptInterface::APP_TRANS_ID . ' = ?', $appTransId)
            ->forUpdate(true);

        $row = $connection->fetchRow($select);

        return $row ?: null;
    }
}

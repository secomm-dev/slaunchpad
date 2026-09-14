<?php
/**
 * Integration-test scaffolding — NOT production code.
 *
 * PaymentAttempt with an empty constructor: AbstractModel's DI constructor
 * (Context/Registry) is unavailable outside a booted Magento app. Every
 * hydration behaviour (setData round-trip, typed getters) is still the REAL
 * production code inherited from PaymentAttempt/DataObject.
 */
declare(strict_types=1);

namespace Secomm\ZaloPay\It;

use Secomm\ZaloPay\Api\Data\PaymentAttemptInterface;
use Secomm\ZaloPay\Model\PaymentAttempt;

class ItAttempt extends PaymentAttempt
{
    /**
     * Skip AbstractModel's Context/Registry DI, but bind the id field name
     * exactly as production does: PaymentAttempt::_construct() ->
     * _init(PaymentAttemptResource::class) sets _idFieldName from the
     * resource's getIdFieldName() ('entity_id', via ObjectManager).
     *
     * @return void
     */
    public function __construct()
    {
        $this->_idFieldName = PaymentAttemptInterface::ENTITY_ID;
    }
}

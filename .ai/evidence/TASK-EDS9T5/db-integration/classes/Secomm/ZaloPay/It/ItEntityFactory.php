<?php
/**
 * Integration-test scaffolding — NOT production code.
 *
 * EntityFactory that constructs collection items without the ObjectManager:
 * PaymentAttempt items are built as ItAttempt (empty ctor, REAL hydration
 * methods); any other class falls back to plain `new`.
 */
declare(strict_types=1);

namespace Secomm\ZaloPay\It;

use Magento\Framework\Data\Collection\EntityFactoryInterface;
use Secomm\ZaloPay\Model\PaymentAttempt;

class ItEntityFactory implements EntityFactoryInterface
{
    /**
     * @param string $className
     * @param array $data
     * @return object
     */
    public function create($className, array $data = [])
    {
        if ($className === PaymentAttempt::class) {
            return new ItAttempt();
        }

        return new $className();
    }
}

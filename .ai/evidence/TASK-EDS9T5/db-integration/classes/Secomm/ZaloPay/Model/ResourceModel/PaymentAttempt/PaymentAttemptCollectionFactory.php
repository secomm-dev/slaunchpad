<?php
/**
 * Integration-test scaffolding — NOT production code.
 *
 * Minimal equivalent of the generated
 * Secomm\ZaloPay\Model\ResourceModel\PaymentAttempt\PaymentAttemptCollectionFactory
 * (generated factories are not in git). create() returns a REAL
 * PaymentAttemptCollection: only _construct() is replaced (it would
 * otherwise pull the ObjectManager for the resource-model registration);
 * every filter/order/limit/render/execute/hydrate call on it is the real
 * production + vendor code path against the real database.
 */
declare(strict_types=1);

namespace Secomm\ZaloPay\Model\ResourceModel\PaymentAttempt;

use Magento\Framework\Data\Collection\Db\FetchStrategy\Query;
use Magento\Framework\DB\Adapter\Pdo\Mysql as PdoMysqlAdapter;
use Magento\Framework\Event\ManagerInterface as EventManagerInterface;
use Psr\Log\NullLogger;
use Secomm\ZaloPay\It\ItEntityFactory;
use Secomm\ZaloPay\It\ItResource;
use Secomm\ZaloPay\Model\PaymentAttempt;

class PaymentAttemptCollectionFactory
{
    /**
     * Real adapter, injected by the driver script.
     *
     * @var PdoMysqlAdapter|null
     */
    public static ?PdoMysqlAdapter $adapter = null;

    /**
     * Real main-table resource, injected by the driver script.
     *
     * @var ItResource|null
     */
    public static ?ItResource $resource = null;

    /**
     * Every collection the factory created (debug capture).
     *
     * @var PaymentAttemptCollection[]
     */
    public static array $created = [];

    /**
     * @param array $data
     * @return PaymentAttemptCollection
     */
    public function create(array $data = []): PaymentAttemptCollection
    {
        $adapter = self::$adapter;
        $resource = self::$resource;
        if ($adapter === null || $resource === null) {
            throw new \RuntimeException('Set PaymentAttemptCollectionFactory::$adapter/$resource first.');
        }

        $collection = new class (
            new ItEntityFactory(),
            new NullLogger(),
            new Query(), // real fetch strategy: $select->getConnection()->fetchAll($select)
            new class () implements EventManagerInterface {
                // No events outside a booted app; the collection's event calls are inert.
                public function dispatch($eventName, array $data = [])
                {
                    return null;
                }

                public function trigger($eventName, array $data = [])
                {
                    return null;
                }
            },
            $adapter,
            $resource
        ) extends PaymentAttemptCollection {
            /**
             * Set the hydrated item class directly; the production
             * _construct() would route through ObjectManager::create().
             *
             * @return void
             */
            protected function _construct(): void
            {
                $this->_itemObjectClass = PaymentAttempt::class;
            }
        };
        self::$created[] = $collection;

        return $collection;
    }
}

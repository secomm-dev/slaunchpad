<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\ZaloPay\Test\Unit\Model;

use Magento\Framework\Data\Collection\Db\FetchStrategyInterface;
use Magento\Framework\Data\Collection\EntityFactoryInterface;
use Magento\Framework\DB\Adapter\Pdo\Mysql as PdoMysqlAdapter;
use Magento\Framework\DB\Select;
use Magento\Framework\DB\Select\SelectRenderer;
use Magento\Framework\Event\ManagerInterface as EventManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Secomm\ZaloPay\Api\Data\PaymentAttemptInterface;
use Secomm\ZaloPay\Model\PaymentAttempt;
use Secomm\ZaloPay\Model\PaymentAttemptFactory;
use Secomm\ZaloPay\Model\PaymentAttemptRepository;
use Secomm\ZaloPay\Model\ResourceModel\PaymentAttempt\PaymentAttemptCollection;
use Secomm\ZaloPay\Model\ResourceModel\PaymentAttempt\PaymentAttemptCollectionFactory;
use Secomm\ZaloPay\Model\ResourceModel\PaymentAttemptResource;

/**
 * PaymentAttemptRepository::getBlockingAttemptByQuoteId — the double-payment
 * guard's blocking lookup (corrective round 4, Blocker 2; corrective round 5:
 * valid AbstractDb OR syntax).
 *
 * Two complementary levels of proof:
 *  1. SIGNATURE: the repository invokes the AbstractDb PARALLEL-ARRAY OR form
 *     addFieldToFilter([fieldA, fieldB], [conditionA, conditionB]) — the only
 *     valid OR signature on a Db (non-EAV) collection. The previous
 *     EAV-style [['attribute' => ...]] array-of-arrays form is invalid here
 *     and would misbehave at runtime (round-5 Blocker 1).
 *  2. REAL QUERY: the production method runs against a REAL collection class
 *     whose addFieldToFilter path (Framework AbstractDb OR-joining ->
 *     Mysql::prepareSqlCondition -> quoteInto -> ZF1 quoting) executes REAL
 *     vendor code, and the RENDERED WHERE parts of the real Select are
 *     asserted: `quote_id` = 42 AND (`payment_status` IN('paid','finalized')
 *     OR `requires_reconciliation` = 1). Ordinary FAILED/EXPIRED/STALE
 *     statuses appear NOWHERE in the blocking conditions, so they can never
 *     block a retry.
 *
 * The only substituted primitives are the three adapter calls a live PDO
 * handle would serve — select() (Select construction), _connect() (guarded,
 * must never run) and _quote() (re-implemented as the pure ZF1 string-quoting
 * primitive, byte-for-byte). Every other frame on the path is real vendor
 * code executed without a database.
 */
class PaymentAttemptRepositoryTest extends TestCase
{
    /**
     * Build a Pdo\Mysql partial mock: constructor disabled (no PDO handle),
     * ONLY the three PDO-dependent methods stubbed — query building
     * (quoteIdentifier, prepareSqlCondition, quoteInto, quote) runs REAL.
     *
     * @return PdoMysqlAdapter|MockObject
     */
    private function newRealQueryAdapter(): PdoMysqlAdapter
    {
        $adapter = $this->getMockBuilder(PdoMysqlAdapter::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['select', '_connect', '_quote'])
            ->getMock();

        // Real Magento Select objects (SelectRenderer with no part renderers:
        // part-level inspection only — full assembly is integration scope).
        $adapter->method('select')->willReturnCallback(
            static fn (): Select => new Select($adapter, new SelectRenderer([]))
        );
        // A unit test must never open a real PDO connection.
        $adapter->method('_connect')->willReturn(null);
        // The pure ZF1 string-quoting primitive (Zend_Db_Adapter_Abstract::
        // _quote) — the Pdo\Mysql variant would need a live PDO handle.
        $adapter->method('_quote')->willReturnCallback(
            static function ($value) {
                if (is_int($value)) {
                    return $value;
                }
                if (is_float($value)) {
                    return sprintf('%F', $value);
                }

                return "'" . addcslashes((string)$value, "\000\n\r\\'\"\032") . "'";
            }
        );

        return $adapter;
    }

    /**
     * A REAL PaymentAttemptCollection that skips only row hydration:
     * _construct() is no-op'd (setResourceModel() would need the
     * ObjectManager) and getFirstItem() returns a fixed item instead of
     * loading from the database. Every filter/order/limit call stays REAL.
     *
     * @param MockObject $adapter
     * @return PaymentAttemptCollection
     */
    private function newRealCollection(MockObject $adapter): PaymentAttemptCollection
    {
        $resource = $this->getMockBuilder(PaymentAttemptResource::class)
            ->disableOriginalConstructor()
            ->getMock();
        $resource->method('getConnection')->willReturn($adapter);
        $resource->method('getMainTable')->willReturn('secomm_zalopay_payment_attempt');

        $collection = new class (
            $this->createMock(EntityFactoryInterface::class),
            $this->createMock(LoggerInterface::class),
            $this->createMock(FetchStrategyInterface::class),
            $this->createMock(EventManagerInterface::class),
            $adapter,
            $resource
        ) extends PaymentAttemptCollection {
            /**
             * Item the repository's getFirstItem() call receives.
             *
             * @var MockObject|null
             */
            public $firstItem;

            /**
             * Skip the ObjectManager-dependent model/resource registration.
             *
             * @return void
             */
            protected function _construct(): void
            {
            }

            /**
             * Skip the database load; hand back the prepared item.
             *
             * @return MockObject|null
             */
            public function getFirstItem()
            {
                return $this->firstItem;
            }
        };
        $collection->firstItem = $this->createMock(PaymentAttempt::class);

        return $collection;
    }

    /**
     * A real repository against a factory that always yields $collection.
     *
     * @param PaymentAttemptCollection $collection
     * @return PaymentAttemptRepository
     */
    private function newRepository(PaymentAttemptCollection $collection): PaymentAttemptRepository
    {
        $factory = $this->getMockBuilder(PaymentAttemptCollectionFactory::class)
            ->disableOriginalConstructor()
            ->getMock();
        $factory->method('create')->willReturn($collection);

        return new PaymentAttemptRepository(
            $this->getMockBuilder(PaymentAttemptFactory::class)
                ->disableOriginalConstructor()
                ->getMock(),
            $this->getMockBuilder(PaymentAttemptResource::class)
                ->disableOriginalConstructor()
                ->getMock(),
            $factory
        );
    }

    /**
     * Round-5 Blocker 1 (minimum proof): the repository uses the VALID
     * AbstractDb parallel-array OR signature — exactly
     * addFieldToFilter([payment_status, requires_reconciliation],
     * [['in' => [paid, finalized]], ['eq' => 1]]) — plus the bounded-lookup
     * shape (quote_id filter first, latest row first, LIMIT 1).
     *
     * @return void
     */
    public function testBlockingLookupUsesValidAbstractDbParallelArrayOrSignature(): void
    {
        $collection = $this->getMockBuilder(PaymentAttemptCollection::class)
            ->disableOriginalConstructor()
            ->getMock();
        $emptyItem = $this->createMock(PaymentAttempt::class);
        $emptyItem->method('getId')->willReturn(null);
        $collection->method('getFirstItem')->willReturn($emptyItem);
        foreach (['addFieldToFilter', 'setOrder', 'setPageSize'] as $method) {
            $collection->method($method)->willReturnSelf();
        }

        // PHPUnit 10 has no at()/withConsecutive(): record the exact
        // addFieldToFilter arguments in call order instead.
        $filterCalls = [];
        $collection->method('addFieldToFilter')->willReturnCallback(
            function (...$args) use (&$filterCalls, $collection) {
                $filterCalls[] = $args;

                return $collection;
            }
        );
        $collection->expects($this->once())
            ->method('setOrder')
            ->with(PaymentAttemptInterface::ENTITY_ID, 'DESC');
        $collection->expects($this->once())->method('setPageSize')->with(1);

        $repository = $this->newRepository($collection);
        // No row matches (empty collection item) => null, never an exception.
        $this->assertNull($repository->getBlockingAttemptByQuoteId(42));

        $this->assertSame(
            [
                [
                    PaymentAttemptInterface::QUOTE_ID,
                    42,
                ],
                [
                    [
                        PaymentAttemptInterface::PAYMENT_STATUS,
                        PaymentAttemptInterface::REQ_RECONCILIATION,
                    ],
                    [
                        [
                            'in' => [
                                PaymentAttemptInterface::STATUS_PAID,
                                PaymentAttemptInterface::STATUS_FINALIZED,
                            ],
                        ],
                        ['eq' => 1],
                    ],
                ],
            ],
            $filterCalls,
            'Blocking lookup must use the valid AbstractDb parallel-array OR signature'
        );
    }

    /**
     * Round-5 Blocker 1 (real-query proof): the production blocking lookup
     * rendered against the REAL collection filter pipeline. The WHERE parts
     * must be quote_id + the OR group over PAID/FINALIZED and
     * requires_reconciliation, and must NOT reference any ordinary
     * retryable status.
     *
     * @return void
     */
    public function testBlockingLookupRendersRealCollectionSql(): void
    {
        $adapter = $this->newRealQueryAdapter();
        $collection = $this->newRealCollection($adapter);

        $repository = $this->newRepository($collection);
        $this->assertNull($repository->getBlockingAttemptByQuoteId(42));

        $where = $collection->getSelect()->getPart(Select::WHERE);
        $this->assertCount(2, $where, 'Real rendered WHERE parts: ' . var_export($where, true));

        // Part 0: `quote_id` = 42.
        $this->assertMatchesRegularExpression(
            '/`quote_id`\s*=\s*\'?42\'?/',
            (string)$where[0],
            'quote_id equality missing from real WHERE: ' . var_export($where, true)
        );

        // Part 1: (`payment_status` IN('paid','finalized')) OR
        //         (`requires_reconciliation` = 1) — the AbstractDb
        //         parallel-array OR join, rendered by real vendor code.
        $orPart = (string)$where[1];
        $this->assertMatchesRegularExpression(
            '/`payment_status`\s*IN\s*\(\s*\'paid\'\s*,\s*\'finalized\'\s*\)/',
            $orPart,
            'PAID/FINALIZED IN-list missing from real WHERE: ' . var_export($where, true)
        );
        $this->assertMatchesRegularExpression(
            '/\)\s*OR\s*\(\s*`requires_reconciliation`\s*=\s*\'?1\'?\s*\)\s*\)/',
            $orPart,
            'requires_reconciliation OR-branch missing from real WHERE: ' . var_export($where, true)
        );

        // Ordinary retryable statuses must appear NOWHERE in the blocking
        // conditions — otherwise Start would refuse a legitimate retry.
        $renderedWhere = strtolower(var_export($where, true));
        $this->assertStringNotContainsString("'failed'", $renderedWhere);
        $this->assertStringNotContainsString("'expired'", $renderedWhere);
        $this->assertStringNotContainsString("'stale'", $renderedWhere);

        // The lookup is bound to the attempt table's main_table alias.
        $from = $collection->getSelect()->getPart(Select::FROM);
        $this->assertArrayHasKey('main_table', $from);
    }
}

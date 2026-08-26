<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\ZaloPay\Test\Integration\Model;

use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\ObjectManagerInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteFactory;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use Secomm\ZaloPay\Api\Data\PaymentAttemptInterface;
use Secomm\ZaloPay\Api\PaymentAttemptRepositoryInterface;
use Secomm\ZaloPay\Model\PaymentAttemptFactory;

/**
 * ZALOPAY-PAYMENT-FIRST Phase 1: persistence layer against the real test DB
 * (no HTTP). Verifies the DB-level idempotency guarantees: the app_trans_id
 * UNIQUE constraint is the hard "one provider transaction = one attempt row"
 * boundary and the lookups used by Start/Return/IPN resolve correctly.
 *
 * @magentoDbIsolation enabled
 */
class PaymentAttemptRepositoryTest extends TestCase
{
    /**
     * @var ObjectManagerInterface
     */
    private $objectManager;

    /**
     * @var PaymentAttemptRepositoryInterface
     */
    private $repository;

    /**
     * @var Quote
     */
    private $quote;

    protected function setUp(): void
    {
        $this->objectManager = Bootstrap::getObjectManager();
        $this->repository = $this->objectManager->get(PaymentAttemptRepositoryInterface::class);
        $this->quote = $this->objectManager->create(Quote::class);
        $this->quote->setStoreId(1)
            ->setReservedOrderId('000000901')
            ->setCustomerEmail('quote-first-test@example.com')
            ->setCustomerIsGuest(true)
            ->save();
    }

    /**
     * @return void
     */
    public function testSaveAndGetRoundTrip(): void
    {
        $attempt = $this->newAttempt('260826_1000_000000901');
        $attempt->markActive('https://pay.zalopay.vn/order/it');
        $this->repository->save($attempt);
        $this->assertNotNull($attempt->getEntityId());

        $loaded = $this->repository->get((int)$attempt->getEntityId());
        $this->assertSame((int)$this->quote->getId(), $loaded->getQuoteId());
        $this->assertSame('000000901', $loaded->getReservedOrderId());
        $this->assertSame('260826_1000_000000901', $loaded->getAppTransId());
        $this->assertSame('https://pay.zalopay.vn/order/it', $loaded->getPayUrl());
        $this->assertSame(PaymentAttemptInterface::STATUS_ACTIVE, $loaded->getPaymentStatus());
        $this->assertSame(100000, $loaded->getAmount());
        $this->assertSame(PaymentAttemptInterface::CURRENCY_VND, $loaded->getCurrency());
    }

    /**
     * DB idempotency: the UNIQUE(app_trans_id) constraint rejects a second
     * attempt row for the same provider transaction — not a PHP flag.
     *
     * @return void
     */
    public function testUniqueAppTransIdIsEnforcedByTheDatabase(): void
    {
        $first = $this->newAttempt('260826_1000_000000901');
        $first->markActive('https://pay.zalopay.vn/order/first');
        $this->repository->save($first);

        $second = $this->newAttempt('260826_1000_000000901');
        $second->markActive('https://pay.zalopay.vn/order/second');

        $this->expectException(CouldNotSaveException::class);
        $this->repository->save($second);
    }

    /**
     * @return void
     */
    public function testGetByAppTransIdFindsAndMisses(): void
    {
        $attempt = $this->newAttempt('260826_1000_000000901');
        $this->repository->save($attempt);

        $found = $this->repository->getByAppTransId('260826_1000_000000901');
        $this->assertNotNull($found);
        $this->assertSame((int)$attempt->getEntityId(), (int)$found->getEntityId());

        $this->assertNull($this->repository->getByAppTransId('260826_9999_000000999'));
        $this->assertNull($this->repository->getByAppTransId(''));
    }

    /**
     * Only INITIATED/ACTIVE attempts are reuse candidates, newest first.
     *
     * @return void
     */
    public function testGetActiveByQuoteIdReturnsNewestActiveAttempt(): void
    {
        $stale = $this->newAttempt('260826_1000_000000901');
        $stale->setCreatedAt('2026-08-26 10:00:00');
        $stale->markActive('https://pay.zalopay.vn/order/old')->markStale();
        $this->repository->save($stale);

        $active = $this->newAttempt('260826_1060_000000901');
        $active->setCreatedAt('2026-08-26 10:01:00');
        $active->markActive('https://pay.zalopay.vn/order/new');
        $this->repository->save($active);

        $found = $this->repository->getActiveByQuoteId((int)$this->quote->getId());
        $this->assertNotNull($found);
        $this->assertSame((int)$active->getEntityId(), (int)$found->getEntityId());
        $this->assertSame(PaymentAttemptInterface::STATUS_ACTIVE, $found->getPaymentStatus());
    }

    /**
     * @return void
     */
    public function testGetListByQuoteIdReturnsAllAttemptsOldestFirst(): void
    {
        $first = $this->newAttempt('260826_1000_000000901');
        $first->setCreatedAt('2026-08-26 10:00:00');
        $this->repository->save($first);
        $second = $this->newAttempt('260826_1060_000000901');
        $second->setCreatedAt('2026-08-26 10:01:00');
        $this->repository->save($second);

        $list = $this->repository->getListByQuoteId((int)$this->quote->getId());
        $this->assertCount(2, $list);
        $this->assertSame((int)$first->getEntityId(), (int)$list[0]->getEntityId());
        $this->assertSame((int)$second->getEntityId(), (int)$list[1]->getEntityId());
    }

    /**
     * The FOR UPDATE hydration path used by the finalizer.
     *
     * @return void
     */
    public function testLockByAppTransIdHydratesTheRow(): void
    {
        $attempt = $this->newAttempt('260826_1000_000000901');
        $attempt->markActive('https://pay.zalopay.vn/order/it');
        $this->repository->save($attempt);

        $locked = $this->repository->lockByAppTransId('260826_1000_000000901');
        $this->assertNotNull($locked);
        $this->assertSame((int)$attempt->getEntityId(), $locked->getEntityId());
        $this->assertSame(PaymentAttemptInterface::STATUS_ACTIVE, $locked->getPaymentStatus());

        $this->assertNull($this->repository->lockByAppTransId('260826_9999_000000999'));
    }

    /**
     * @return void
     */
    public function testGetUnknownEntityThrows(): void
    {
        $this->expectException(NoSuchEntityException::class);
        $this->repository->get(999999999);
    }

    /**
     * @param string $appTransId
     * @return PaymentAttemptInterface
     */
    private function newAttempt(string $appTransId): PaymentAttemptInterface
    {
        /** @var PaymentAttemptFactory $factory */
        $factory = $this->objectManager->get(PaymentAttemptFactory::class);
        $attempt = $factory->create();
        $attempt->setQuoteId((int)$this->quote->getId());
        $attempt->setReservedOrderId('000000901');
        $attempt->setAppTransId($appTransId);
        $attempt->setAmount(100000);
        $attempt->setCurrency(PaymentAttemptInterface::CURRENCY_VND);
        $attempt->setStoreId(1);
        $attempt->setExpiresAt('2099-01-01 00:00:00');

        return $attempt;
    }
}

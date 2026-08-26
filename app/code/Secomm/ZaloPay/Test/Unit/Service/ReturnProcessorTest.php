<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\ZaloPay\Test\Unit\Service;

use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Gateway\Command\CommandPoolInterface;
use Magento\Payment\Gateway\Command\ResultInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\ZaloPay\Api\Data\PaymentAttemptInterface;
use Secomm\ZaloPay\Api\PaymentAttemptRepositoryInterface;
use Secomm\ZaloPay\Gateway\Validator\AbstractResponseValidator;
use Secomm\ZaloPay\Helper\Data as ZaloPayHelper;
use Secomm\ZaloPay\Logger\Logger;
use Secomm\ZaloPay\Model\PaymentAttempt;
use Secomm\ZaloPay\Service\OrderFinalizer;
use Secomm\ZaloPay\Service\ReturnProcessor;

/**
 * ZALOPAY-PAYMENT-FIRST Phase 1 return-redirect contract: the browser
 * redirect is NOT payment proof — the authoritative v2/query decides.
 * Includes the explicit ACTIVE -> PAID transition before finalization and
 * the amount lock against the persisted snapshot.
 */
class ReturnProcessorTest extends TestCase
{
    /**
     * @var PaymentAttemptRepositoryInterface|MockObject
     */
    private $repository;

    /**
     * @var CommandPoolInterface|MockObject
     */
    private $commandPool;

    /**
     * @var OrderFinalizer|MockObject
     */
    private $orderFinalizer;

    /**
     * @var ZaloPayHelper|MockObject
     */
    private $helper;

    /**
     * @var ReturnProcessor
     */
    private $processor;

    /**
     * @var PaymentAttempt|null
     */
    private ?PaymentAttempt $saved = null;

    private const APP_TRANS_ID = '260826_1000_000000123';

    protected function setUp(): void
    {
        $this->repository = $this->createMock(PaymentAttemptRepositoryInterface::class);
        $this->commandPool = $this->createMock(CommandPoolInterface::class);
        $this->orderFinalizer = $this->createMock(OrderFinalizer::class);
        $this->helper = $this->createMock(ZaloPayHelper::class);
        $this->processor = new ReturnProcessor(
            $this->repository,
            $this->commandPool,
            $this->orderFinalizer,
            $this->helper,
            $this->createMock(Logger::class)
        );
    }

    /**
     * Happy path: status=1, v2/query confirms 1 with the exact snapshot
     * amount — the attempt transitions ACTIVE -> PAID explicitly, then the
     * finalizer receives it with the provider transaction id.
     *
     * @return void
     */
    public function testVerifiedPaymentTransitionsPaidThenFinalizes(): void
    {
        $attempt = $this->newActiveAttempt();
        $this->repository->method('getByAppTransId')->willReturn($attempt);
        $this->stubSave();
        $this->stubQuery(['return_code' => 1, 'amount' => 100000, 'zp_trans_id' => '240801000001']);
        $this->orderFinalizer->expects($this->once())
            ->method('finalize')
            ->with($attempt, '240801000001');

        $result = $this->processor->process($this->returnParams(['status' => 1]));

        $this->assertSame('checkout/onepage/success', $result);
        $this->assertSame(PaymentAttemptInterface::STATUS_PAID, $this->saved->getPaymentStatus());
        $this->assertSame('240801000001', $this->saved->getProviderTransactionId());
    }

    /**
     * When the IPN arrived first the attempt is already PAID — no duplicate
     * PAID transition, finalizer still runs exactly once.
     *
     * @return void
     */
    public function testIpnPaidAttemptIsFinalizedWithoutDoubleTransition(): void
    {
        $attempt = $this->newActiveAttempt()->markPaid('240801000001');
        $this->repository->method('getByAppTransId')->willReturn($attempt);
        $this->repository->expects($this->never())->method('save');
        $this->stubQuery(['return_code' => 1, 'amount' => 100000, 'zp_trans_id' => '240801000001']);
        $this->orderFinalizer->expects($this->once())->method('finalize')->with($attempt, '240801000001');

        $result = $this->processor->process($this->returnParams(['status' => 1]));
        $this->assertSame('checkout/onepage/success', $result);
    }

    /**
     * Duplicate return on a FINALIZED attempt: same resulting state, no
     * provider call, no finalizer call.
     *
     * @return void
     */
    public function testDuplicateReturnOnFinalizedAttemptShortCircuitsToSuccess(): void
    {
        $attempt = $this->newActiveAttempt()->markPaid()->markFinalized(77);
        $this->repository->method('getByAppTransId')->willReturn($attempt);
        $this->commandPool->expects($this->never())->method('get');
        $this->orderFinalizer->expects($this->never())->method('finalize');

        $this->assertSame('checkout/onepage/success', $this->processor->process($this->returnParams(['status' => 1])));
    }

    /**
     * Provider redirect says "not paid": explicit FAILED transition with the
     * reason, customer-safe exception.
     *
     * @return void
     */
    public function testUnpaidReturnStatusMarksAttemptFailed(): void
    {
        $attempt = $this->newActiveAttempt();
        $this->repository->method('getByAppTransId')->willReturn($attempt);
        $this->stubSave();

        try {
            $this->processor->process($this->returnParams(['status' => 0]));
            $this->fail('Expected LocalizedException for unpaid status.');
        } catch (LocalizedException $e) {
            $this->assertStringContainsString('not completed', $e->getMessage());
        }
        $this->assertSame(PaymentAttemptInterface::STATUS_FAILED, $this->saved->getPaymentStatus());
        $this->assertSame('cancelled', $this->saved->getProviderStatus());
        $this->assertStringContainsString('ZaloPay return status 0', (string)$this->saved->getLastError());
    }

    /**
     * Redirect params are signed with key2 — a bad checksum stops the flow
     * before any state change.
     *
     * @return void
     */
    public function testChecksumMismatchStopsProcessing(): void
    {
        $attempt = $this->newActiveAttempt();
        $this->repository->method('getByAppTransId')->willReturn($attempt);
        $this->repository->expects($this->never())->method('save');
        $this->helper->method('verifyRedirect')->willReturn(false);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Transaction verification failed.');
        $this->processor->process($this->returnParams(['status' => 1, 'checksum' => 'bad']));
    }

    /**
     * Amount lock: provider holds the money for a DIFFERENT amount than the
     * persisted snapshot — PAID + explicit error, NO automatic order
     * placement (the finalizer must not run).
     *
     * @return void
     */
    public function testAmountMismatchNeverPlacesOrder(): void
    {
        $attempt = $this->newActiveAttempt();
        $this->repository->method('getByAppTransId')->willReturn($attempt);
        $this->stubSave();
        $this->stubQuery(['return_code' => 1, 'amount' => 50000, 'zp_trans_id' => '240801000001']);
        $this->orderFinalizer->expects($this->never())->method('finalize');

        try {
            $this->processor->process($this->returnParams(['status' => 1]));
            $this->fail('Expected LocalizedException for amount mismatch.');
        } catch (LocalizedException $e) {
            $this->assertStringContainsString('amount mismatch', $e->getMessage());
        }
        $this->assertSame(PaymentAttemptInterface::STATUS_PAID, $this->saved->getPaymentStatus());
        $this->assertStringContainsString('paid 50000, snapshot 100000', (string)$this->saved->getLastError());
    }

    /**
     * v2/query still processing (return_code 3): the attempt stays ACTIVE —
     * TTL / IPN / Phase 2 cron resolve it later.
     *
     * @return void
     */
    public function testQueryProcessingKeepsAttemptActive(): void
    {
        $attempt = $this->newActiveAttempt();
        $this->repository->method('getByAppTransId')->willReturn($attempt);
        $this->repository->expects($this->never())->method('save');
        $this->stubQuery(['return_code' => 3, 'amount' => 100000, 'zp_trans_id' => '']);
        $this->orderFinalizer->expects($this->never())->method('finalize');

        try {
            $this->processor->process($this->returnParams(['status' => 1]));
            $this->fail('Expected LocalizedException while provider still processing.');
        } catch (LocalizedException $e) {
            $this->assertStringContainsString('still being processed', $e->getMessage());
        }
        $this->assertSame(PaymentAttemptInterface::STATUS_ACTIVE, $attempt->getPaymentStatus());
    }

    /**
     * v2/query hard failure: customer-safe exception, no state change.
     *
     * @return void
     */
    public function testQueryFailureThrowsLocalizedException(): void
    {
        $attempt = $this->newActiveAttempt();
        $this->repository->method('getByAppTransId')->willReturn($attempt);
        $command = $this->createMock(\Magento\Payment\Gateway\CommandInterface::class);
        $command->method('execute')->willThrowException(new \Exception('HTTP 500'));
        $this->commandPool->method('get')->willReturn($command);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('could not be verified');
        $this->processor->process($this->returnParams(['status' => 1]));
    }

    /**
     * Unknown apptransid: no attempt, no order assumption.
     *
     * @return void
     */
    public function testUnknownAttemptThrowsSessionNotFound(): void
    {
        $this->repository->method('getByAppTransId')->willReturn(null);
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('session not found');
        $this->processor->process($this->returnParams(['status' => 1]));
    }

    /**
     * @param array $overrides
     * @return array
     */
    private function returnParams(array $overrides): array
    {
        return array_merge(
            ['apptransid' => self::APP_TRANS_ID, 'status' => 1],
            $overrides
        );
    }

    /**
     * @param array $response
     * @return void
     */
    private function stubQuery(array $response): void
    {
        $result = $this->createMock(ResultInterface::class);
        $result->method('get')->willReturn($response);
        $command = $this->createMock(\Magento\Payment\Gateway\CommandInterface::class);
        $command->method('execute')->willReturn($result);
        $this->commandPool->method('get')->with('query_transaction')->willReturn($command);
    }

    /**
     * @return void
     */
    private function stubSave(): void
    {
        $this->saved = null;
        $this->repository->method('save')->willReturnCallback(
            function (PaymentAttemptInterface $attempt) {
                $this->saved = $attempt;

                return $attempt;
            }
        );
    }

    /**
     * @return PaymentAttempt
     */
    private function newActiveAttempt(): PaymentAttempt
    {
        $attempt = $this->newAttemptModel();
        $attempt->setEntityId(9);
        $attempt->setQuoteId(42);
        $attempt->setReservedOrderId('000000123');
        $attempt->setAppTransId(self::APP_TRANS_ID);
        $attempt->setAmount(100000);
        $attempt->setCurrency(PaymentAttemptInterface::CURRENCY_VND);
        $attempt->setExpiresAt('2099-01-01 00:00:00');
        $attempt->setPaymentStatus(PaymentAttemptInterface::STATUS_INITIATED);
        $attempt->markActive('https://pay.zalopay.vn/order/abc');

        return $attempt;
    }

    /**
     * PaymentAttempt extends AbstractModel — constructor needs Context/Registry.
     *
     * @return PaymentAttempt
     */
    private function newAttemptModel(): PaymentAttempt
    {
        return new PaymentAttempt(
            $this->createMock(\Magento\Framework\Model\Context::class),
            $this->createMock(\Magento\Framework\Registry::class),
            $this->newResourceStub()
        );
    }

    /**
     * An injected resource keeps _init() away from the (unit-test absent)
     * ObjectManager while providing the entity id field name.
     *
     * @return \Secomm\ZaloPay\Model\ResourceModel\PaymentAttempt|\PHPUnit\Framework\MockObject\MockObject
     */
    private function newResourceStub()
    {
        $resource = $this->getMockBuilder(\Secomm\ZaloPay\Model\ResourceModel\PaymentAttemptResource::class)
            ->disableOriginalConstructor()
            ->getMock();
        $resource->method('getIdFieldName')->willReturn(PaymentAttemptInterface::ENTITY_ID);

        return $resource;
    }
}

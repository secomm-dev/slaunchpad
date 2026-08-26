<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\ZaloPay\Test\Unit\Service;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\ZaloPay\Api\Data\PaymentAttemptInterface;
use Secomm\ZaloPay\Api\PaymentAttemptRepositoryInterface;
use Secomm\ZaloPay\Gateway\Helper\Authorization;
use Secomm\ZaloPay\Gateway\Request\AbstractDataBuilder;
use Secomm\ZaloPay\Gateway\Validator\AbstractResponseValidator;
use Secomm\ZaloPay\Logger\Logger;
use Secomm\ZaloPay\Model\PaymentAttempt;
use Secomm\ZaloPay\Service\IpnProcessor;

/**
 * ZALOPAY-PAYMENT-FIRST Phase 1 IPN contract (cases 9 and 10):
 *  - an IPN arriving BEFORE the order exists is a VALID state: the attempt
 *    becomes PAID with order_id NULL and NO order is placed from the IPN;
 *  - a duplicate IPN on FINALIZED yields the same state and is acknowledged;
 *  - MAC failure -> 500 (ZaloPay retries); amount mismatch -> PAID +
 *    explicit reconciliation error + 200; late callbacks on terminal states
 *    are recorded, never resurrected;
 *  - payloads without a payment attempt fall back to the legacy flow (null).
 */
class IpnProcessorTest extends TestCase
{
    /**
     * @var PaymentAttemptRepositoryInterface|MockObject
     */
    private $repository;

    /**
     * @var Authorization|MockObject
     */
    private $authorization;

    /**
     * @var IpnProcessor
     */
    private $processor;

    /**
     * @var PaymentAttempt|null Attempt captured by the save stub.
     */
    private ?PaymentAttempt $saved = null;

    private const DATA_STRING = '{"app_id":2554,"app_trans_id":"260826_1000_000000123"}';
    private const VALID_MAC = 'valid-mac';
    private const APP_TRANS_ID = '260826_1000_000000123';

    protected function setUp(): void
    {
        $this->repository = $this->createMock(PaymentAttemptRepositoryInterface::class);
        $this->authorization = $this->createMock(Authorization::class);
        $this->authorization->method('getMacKey2')->willReturn(self::VALID_MAC);
        $this->processor = new IpnProcessor(
            $this->repository,
            $this->authorization,
            $this->createMock(Logger::class)
        );
    }

    /**
     * Case 9: IPN before Return — attempt found, no order yet. It becomes
     * PAID with the provider transaction id, order placement NEVER happens
     * in the IPN context, and ZaloPay gets a 200 ack.
     *
     * @return void
     */
    public function testIpnBeforeReturnMarksAttemptPaidWithoutPlacingOrder(): void
    {
        $attempt = $this->newActiveAttempt();
        $this->stubAttempt($attempt);
        $this->stubSave();

        $result = $this->processor->process($this->payload(['zp_trans_id' => '240801000001', 'amount' => 100000]));

        $this->assertSame(200, $result['http_code']);
        $this->assertFalse($result['errors']);
        $this->assertSame(PaymentAttemptInterface::STATUS_PAID, $this->saved->getPaymentStatus());
        $this->assertSame('240801000001', $this->saved->getProviderTransactionId());
        // No order was bound by the IPN.
        $this->assertNull($this->saved->getOrderId());
        $this->assertSame(PaymentAttemptInterface::STATUS_PAID, $attempt->getPaymentStatus());
    }

    /**
     * Case 10: duplicate IPN on a FINALIZED attempt — same resulting state,
     * acknowledged, nothing saved.
     *
     * @return void
     */
    public function testDuplicateIpnOnFinalizedAttemptIsAcknowledgedWithoutChange(): void
    {
        $attempt = $this->newActiveAttempt()->markPaid('240801000001')->markFinalized(77);
        $this->stubAttempt($attempt);
        $this->repository->expects($this->never())->method('save');

        $result = $this->processor->process($this->payload(['zp_trans_id' => '240801000001', 'amount' => 100000]));

        $this->assertSame(200, $result['http_code']);
        $this->assertFalse($result['errors']);
        $this->assertSame(PaymentAttemptInterface::STATUS_FINALIZED, $attempt->getPaymentStatus());
        $this->assertSame(77, $attempt->getOrderId());
    }

    /**
     * MAC failure must be a 500 so ZaloPay retries the callback.
     *
     * @return void
     */
    public function testMacFailureReturns500(): void
    {
        $this->stubAttempt($this->newActiveAttempt());

        $payload = $this->payload(['zp_trans_id' => '240801000001', 'amount' => 100000]);
        $payload['mac'] = 'tampered';

        $result = $this->processor->process($payload);
        $this->assertSame(500, $result['http_code']);
        $this->assertTrue($result['errors']);
    }

    /**
     * Amount mismatch: PAID + explicit last_error, acknowledged for
     * reconciliation — the payment is real money, the state records it.
     *
     * @return void
     */
    public function testAmountMismatchMarksPaidAndRecordsReconciliationError(): void
    {
        $attempt = $this->newActiveAttempt();
        $this->stubAttempt($attempt);
        $this->stubSave();

        $result = $this->processor->process($this->payload(['zp_trans_id' => '240801000001', 'amount' => 50000]));

        $this->assertSame(200, $result['http_code']);
        $this->assertFalse($result['errors']);
        $this->assertSame(PaymentAttemptInterface::STATUS_PAID, $this->saved->getPaymentStatus());
        $this->assertStringContainsString(
            'IPN amount mismatch: paid 50000, snapshot 100000',
            (string)$this->saved->getLastError()
        );
        $this->assertNull($this->saved->getOrderId());
    }

    /**
     * A late paid callback on a terminal attempt is recorded, not applied.
     *
     * @return void
     */
    public function testLateCallbackOnStaleAttemptIsRecordedOnly(): void
    {
        $attempt = $this->newActiveAttempt()->markStale();
        $this->stubAttempt($attempt);
        $this->repository->expects($this->never())->method('save');

        $result = $this->processor->process($this->payload(['zp_trans_id' => '240801000001', 'amount' => 100000]));

        $this->assertSame(200, $result['http_code']);
        $this->assertSame(PaymentAttemptInterface::STATUS_STALE, $attempt->getPaymentStatus());
    }

    /**
     * No attempt for the app_trans_id -> legacy order-first flow takes over.
     *
     * @return void
     */
    public function testUnknownAttemptFallsBackToLegacyFlow(): void
    {
        $this->repository->method('getByAppTransId')->willReturn(null);
        $this->assertNull($this->processor->process($this->payload(['amount' => 100000])));
    }

    /**
     * @param array $transData
     * @return array
     */
    private function payload(array $transData): array
    {
        return [
            'data' => self::DATA_STRING,
            'mac' => self::VALID_MAC,
            'trans_data' => array_merge(
                [AbstractDataBuilder::APP_TRANS_ID => self::APP_TRANS_ID],
                $transData
            ),
            AbstractDataBuilder::APP_TRANS_ID => self::APP_TRANS_ID,
            AbstractResponseValidator::ZP_TRANS_ID => $transData['zp_trans_id'] ?? '',
        ];
    }

    /**
     * @param PaymentAttempt $attempt
     * @return void
     */
    private function stubAttempt(PaymentAttempt $attempt): void
    {
        $this->repository->method('getByAppTransId')->willReturn($attempt);
    }

    /**
     * @return void
     */
    private function stubSave(): void
    {
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

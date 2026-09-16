<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\ZaloPay\Test\Unit\Gateway\Command;

use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Http\TransferFactoryInterface;
use Magento\Payment\Gateway\Http\TransferInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\Payment as OrderPayment;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\ZaloPay\Api\Data\RefundInterface;
use Secomm\ZaloPay\Api\Data\RefundInterfaceFactory;
use Secomm\ZaloPay\Command\Refund\SaveCommand;
use Secomm\ZaloPay\Gateway\Command\RefundCommand;
use Secomm\ZaloPay\Gateway\Command\RefundQueryCommand;
use Secomm\ZaloPay\Gateway\Helper\Rate;
use Secomm\ZaloPay\Logger\Logger;
use Secomm\ZaloPay\Model\Data\RefundData;

/**
 * REFUND CMD 16-23 (TASK-CG6BM7): RefundCommand contract after the
 * LocalizedException-only + provider-map messaging rewrite:
 *
 *  - 1 (and 3->query->1)   -> success message, no pending row persistence;
 *  - 3 -> query 3          -> PROCESSING: no throw, creditmemo PROCESSING,
 *    pending refund row (NOT_PROCESSED) persisted for the bounded cron;
 *  - 2 / protocol anomaly  -> LocalizedException (Payment::refund's caught
 *    type) with a provider-map message on the CURRENT response;
 *  - transport failure     -> raw exception text NEVER reaches the UI
 *    message; the pending PROCESSING row still persists in finally.
 */
class RefundCommandTest extends TestCase
{
    private const M_REFUND_ID = '260916_1000_777';

    private BuilderInterface|MockObject $requestBuilder;

    private TransferFactoryInterface|MockObject $transferFactory;

    private ClientInterface|MockObject $client;

    private Logger|MockObject $logger;

    private RefundQueryCommand|MockObject $refundQueryCommand;

    private SaveCommand|MockObject $saveCommand;

    private RefundData $refundRow;

    private OrderPayment|MockObject $payment;

    private Creditmemo|MockObject $creditmemo;

    private ManagerInterface|MockObject $messageManager;

    private Json|MockObject $serializer;

    private Rate|MockObject $rate;

    private RefundCommand $command;

    protected function setUp(): void
    {
        $this->requestBuilder = $this->createMock(BuilderInterface::class);
        $this->transferFactory = $this->createMock(\Magento\Payment\Gateway\Http\TransferFactoryInterface::class);
        $this->client = $this->createMock(ClientInterface::class);
        $this->logger = $this->getMockBuilder(Logger::class)->disableOriginalConstructor()->getMock();
        $this->refundQueryCommand = $this->createMock(RefundQueryCommand::class);
        $this->saveCommand = $this->createMock(SaveCommand::class);
        $this->messageManager = $this->createMock(ManagerInterface::class);
        $this->serializer = $this->createMock(Json::class);
        $this->rate = $this->createMock(Rate::class);

        $this->refundRow = new RefundData();

        $refundFactory = $this->createMock(RefundInterfaceFactory::class);
        $refundFactory->method('create')->willReturn($this->refundRow);

        $creditmemoOrder = $this->createMock(Order::class);
        $creditmemoOrder->method('getId')->willReturn(55);

        $this->creditmemo = $this->createMock(Creditmemo::class);
        $this->creditmemo->method('getOrder')->willReturn($creditmemoOrder);
        $this->creditmemo->method('getId')->willReturn(55);

        $this->payment = $this->createMock(OrderPayment::class);
        $this->payment->method('getCreditmemo')->willReturn($this->creditmemo);
        $this->payment->method('getOrder')->willReturn($creditmemoOrder);

        $paymentDO = $this->createMock(PaymentDataObjectInterface::class);
        $paymentDO->method('getPayment')->willReturn($this->payment);

        $this->command = new RefundCommand(
            $this->requestBuilder,
            $this->transferFactory,
            $this->client,
            $this->logger,
            $this->refundQueryCommand,
            $this->saveCommand,
            $refundFactory,
            $this->createMock(\Magento\Framework\App\RequestInterface::class),
            $this->messageManager,
            $this->rate,
            $this->serializer
        );
    }

    /**
     * @param float $amount
     * @return array
     */
    private function commandSubject(float $amount = 1685000.0): array
    {
        $paymentDO = $this->createMock(PaymentDataObjectInterface::class);
        $paymentDO->method('getPayment')->willReturn($this->payment);

        return [
            'payment' => $paymentDO,
            'amount' => $amount,
        ];
    }

    /**
     * Stub the request-build chain: builder output carries m_refund_id; the
     * transfer echoes it as its body.
     *
     * @return void
     */
    private function stubBuildChain(): void
    {
        $requestData = [
            'app_id' => '1000',
            RefundInterface::M_REFUND_ID => self::M_REFUND_ID,
            'timestamp' => '1690000000000',
        ];
        $this->requestBuilder->method('build')->willReturn($requestData);

        $transfer = $this->createMock(TransferInterface::class);
        $transfer->method('getBody')->willReturn($requestData);
        $this->transferFactory->method('create')->willReturn($transfer);
    }

    /**
     * Stub the amount conversion (VND, 1:1).
     *
     * @return void
     */
    private function stubRate(): void
    {
        $this->rate->method('getVndAmount')->willReturn(1685000.0);
    }

    /**
     * REFUND CMD 16: return_code 1 -> success message, refund row marked
     * PROCESSED in-memory, NO pending-row persistence, no throw.
     */
    public function testSuccessResponseMarksRowProcessedAndSucceeds(): void
    {
        $this->stubBuildChain();
        $this->stubRate();
        $this->client->method('placeRequest')->willReturn(
            ['return_code' => 1, 'return_message' => 'Refund successful.', 'refund_id' => 12345]
        );

        $this->messageManager->expects($this->once())->method('addSuccessMessage')
            ->with($this->stringContains('Zalopay: Refund successful'));
        $this->saveCommand->expects($this->never())->method('execute');
        $this->creditmemo->expects($this->never())->method('save');
        $this->creditmemo->expects($this->never())->method('setState');

        $this->command->execute($this->commandSubject());

        $this->assertTrue($this->refundRow->getIsProcessed());
    }

    /**
     * REFUND CMD 17: refund PROCESSING -> query SUCCESS -> success message,
     * no pending-row persistence (the query already settled it).
     */
    public function testProcessingThenQuerySuccessSucceeds(): void
    {
        $this->stubBuildChain();
        $this->stubRate();
        $this->client->method('placeRequest')->willReturn(
            ['return_code' => 3, 'return_message' => 'processing']
        );

        $this->refundQueryCommand->method('setZaloRefundId')->with(self::M_REFUND_ID)->willReturnSelf();
        $this->refundQueryCommand->method('buildRequestData')->willReturn(
            ['app_id' => '1000', 'm_refund_id' => self::M_REFUND_ID, 'timestamp' => 1]
        );
        $this->refundQueryCommand->method('getRefundQuery')->willReturn(
            ['return_code' => 1, 'return_message' => 'Refund successful.']
        );

        $this->messageManager->expects($this->once())->method('addSuccessMessage')
            ->with($this->stringContains('Refund successful'));
        $this->saveCommand->expects($this->never())->method('execute');

        $this->command->execute($this->commandSubject());
    }

    /**
     * REFUND CMD 18: refund PROCESSING -> query still PROCESSING -> no
     * throw; the pending refund row (NOT_PROCESSED) is persisted with the
     * signed query subject for the bounded cron, and the creditmemo moves
     * to PROCESSING.
     */
    public function testProcessingThenProcessingPersistsPendingRow(): void
    {
        $this->stubBuildChain();
        $this->stubRate();
        $this->client->method('placeRequest')->willReturn(
            ['return_code' => 3, 'return_message' => 'processing']
        );

        $querySubject = ['app_id' => '1000', 'm_refund_id' => self::M_REFUND_ID, 'timestamp' => 1];
        $this->refundQueryCommand->method('setZaloRefundId')->with(self::M_REFUND_ID)->willReturnSelf();
        $this->refundQueryCommand->method('buildRequestData')->willReturn($querySubject);
        $this->refundQueryCommand->method('getRefundQuery')->willReturn(
            ['return_code' => 3, 'return_message' => 'processing']
        );

        $this->serializer->method('serialize')->willReturnCallback(
            static fn (array $data) => json_encode($data)
        );

        $this->messageManager->expects($this->once())->method('addSuccessMessage');
        $this->saveCommand->expects($this->once())->method('execute')->with($this->refundRow);
        $this->creditmemo->expects($this->once())->method('setState')
            ->with(\Secomm\ZaloPay\Plugin\Model\Order\CreditmemoPlugin::STATE_PROCESSING);
        $this->creditmemo->expects($this->once())->method('save');

        $this->command->execute($this->commandSubject());

        $this->assertFalse($this->refundRow->getIsProcessed());
        $this->assertSame(55, $this->refundRow->getCreditMemoId());
        $this->assertNotNull($this->refundRow->getAdditionalInformation());
    }

    /**
     * REFUND CMD 19: return_code 2 (with sub_return_code) -> the failure is
     * a LocalizedException whose message comes from the provider map on the
     * CURRENT response — the raw provider return_message never surfaces.
     */
    public function testFailResponseThrowsLocalizedWithMappedMessage(): void
    {
        $this->stubBuildChain();
        $this->stubRate();
        $this->client->method('placeRequest')->willReturn(
            [
                'return_code' => 2,
                'sub_return_code' => -13,
                'return_message' => 'RAW-PROVIDER-DETAIL',
            ]
        );

        $this->saveCommand->expects($this->never())->method('execute');
        $this->creditmemo->expects($this->never())->method('save');
        $this->messageManager->expects($this->never())->method('addSuccessMessage');

        try {
            $this->command->execute($this->commandSubject());
            $this->fail('LocalizedException was not thrown.');
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $this->assertStringStartsWith('Zalopay: ', $e->getRawMessage());
            $this->assertStringContainsString('Refund time has expired.', $e->getRawMessage());
            $this->assertStringNotContainsString('RAW-PROVIDER-DETAIL', $e->getRawMessage());
        }
    }

    /**
     * REFUND CMD 20: the query call itself fails (transport) while the
     * refund was PROCESSING -> the pending row STILL persists (finally),
     * the creditmemo still moves to PROCESSING, and the thrown
     * LocalizedException carries a generic safe message — never the raw
     * transport text.
     */
    public function testQueryTransportFailurePersistsPendingRowAndThrowsSafeMessage(): void
    {
        $this->stubBuildChain();
        $this->stubRate();
        $this->client->method('placeRequest')->willReturn(
            ['return_code' => 3, 'return_message' => 'processing']
        );

        $this->refundQueryCommand->method('setZaloRefundId')->willReturnSelf();
        $this->refundQueryCommand->method('buildRequestData')->willReturn(
            ['app_id' => '1000', 'm_refund_id' => self::M_REFUND_ID, 'timestamp' => 1]
        );
        $this->refundQueryCommand->method('getRefundQuery')
            ->willThrowException(new \RuntimeException('CURL transport exploded: secret-endpoint'));

        $this->serializer->method('serialize')->willReturnCallback(
            static fn (array $data) => json_encode($data)
        );

        $this->saveCommand->expects($this->once())->method('execute')->with($this->refundRow);
        $this->creditmemo->expects($this->once())->method('save');

        try {
            $this->command->execute($this->commandSubject());
            $this->fail('LocalizedException was not thrown.');
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $this->assertStringStartsWith('Zalopay: ', $e->getRawMessage());
            $this->assertStringNotContainsString('CURL', $e->getRawMessage());
            $this->assertStringNotContainsString('secret-endpoint', $e->getRawMessage());
        }

        $this->assertFalse($this->refundRow->getIsProcessed());
    }

    /**
     * REFUND CMD 21/22: a missing return_code is a protocol anomaly ->
     * explicit LocalizedException failure (never a false success), and the
     * thrown type is exactly what Payment::refund catches.
     */
    public function testMissingReturnCodeThrowsLocalizedException(): void
    {
        $this->stubBuildChain();
        $this->stubRate();
        $this->client->method('placeRequest')->willReturn(
            ['return_message' => 'no code at all']
        );

        $this->saveCommand->expects($this->never())->method('execute');

        try {
            $this->command->execute($this->commandSubject());
            $this->fail('LocalizedException was not thrown.');
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $this->assertInstanceOf(\Magento\Framework\Exception\LocalizedException::class, $e);
            $this->assertStringContainsString('Refund failed. Please try again later.', $e->getRawMessage());
        }
    }

    /**
     * REFUND CMD 23: a generic \Exception (Payment::refund only catches
     * LocalizedException) must NEVER escape with its raw message — it is
     * re-wrapped into a safe LocalizedException.
     */
    public function testInternalExceptionIsRewrappedAsSafeLocalized(): void
    {
        $this->stubBuildChain();
        $this->stubRate();
        $this->client->method('placeRequest')->willThrowException(
            new \RuntimeException('DB deadlocked at /var/www/internal/secret path')
        );

        try {
            $this->command->execute($this->commandSubject());
            $this->fail('LocalizedException was not thrown.');
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $this->assertStringNotContainsString('DB deadlocked', $e->getRawMessage());
            $this->assertStringNotContainsString('secret', $e->getRawMessage());
            $this->assertStringStartsWith('Zalopay: ', $e->getRawMessage());
        }
    }
}

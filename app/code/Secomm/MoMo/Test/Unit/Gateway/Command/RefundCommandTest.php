<?php
/**
 * Unit tests for the MoMo refund gateway command (MOMO-02).
 *
 * Verifies the uncertainty-safety flow: durable identity is opened BEFORE
 * any provider call (a block must never reach MoMo); only echo-verified
 * SUCCESS lets the native handler touch payment accounting; FAILED and
 * UNKNOWN throw (native creditmemo rolls back, no false finalization);
 * transport errors are recorded as UNKNOWN and never retried silently.
 *
 * @author    Secomm Teams
 * @copyright Copyright (c) 2026 Secomm (https://www.secomm.vn)
 * @package   Secomm_MoMo
 */
declare(strict_types=1);

namespace Secomm\MoMo\Test\Unit\Gateway\Command;

use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Gateway\Command\Result\ArrayResult;
use Magento\Payment\Gateway\Command\Result\ArrayResultFactory;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Http\ClientException;
use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Http\ConverterException;
use Magento\Payment\Gateway\Http\TransferFactoryInterface;
use Magento\Payment\Gateway\Http\TransferInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Payment\Gateway\Response\HandlerInterface;
use PHPUnit\Framework\TestCase;
use Secomm\MoMo\Api\Data\RefundRequestInterface;
use Secomm\MoMo\Gateway\Command\RefundCommand;
use Secomm\MoMo\Service\RefundClassification;
use Secomm\MoMo\Service\RefundRequestManager;
use Secomm\MoMo\Service\RefundResultClassifier;

class RefundCommandTest extends TestCase
{
    private const REQUEST_ID = 'MOMO2609180000-SLP-1-RQ2222';

    /**
     * @var RefundRequestManager|\PHPUnit\Framework\MockObject\MockObject
     */
    private $manager;

    /**
     * @var RefundResultClassifier|\PHPUnit\Framework\MockObject\MockObject
     */
    private $classifier;

    /**
     * @var BuilderInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    private $requestBuilder;

    /**
     * @var TransferFactoryInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    private $transferFactory;

    /**
     * @var ClientInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    private $client;

    /**
     * @var HandlerInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    private $handler;

    /**
     * @var ArrayResultFactory|\PHPUnit\Framework\MockObject\MockObject
     */
    private $resultFactory;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->manager = $this->createMock(RefundRequestManager::class);
        $this->classifier = $this->createMock(RefundResultClassifier::class);
        $this->requestBuilder = $this->createMock(BuilderInterface::class);
        $this->transferFactory = $this->createMock(TransferFactoryInterface::class);
        $this->client = $this->createMock(ClientInterface::class);
        $this->handler = $this->createMock(HandlerInterface::class);
        $this->resultFactory = $this->createMock(ArrayResultFactory::class);
    }

    /**
     * @return RefundCommand
     */
    private function command(): RefundCommand
    {
        return new RefundCommand(
            $this->manager,
            $this->classifier,
            $this->requestBuilder,
            $this->transferFactory,
            $this->client,
            $this->handler,
            $this->resultFactory
        );
    }

    /**
     * @param float $amount
     * @return array
     */
    private function subject(float $amount = 150000.0): array
    {
        $paymentDO = $this->createMock(PaymentDataObjectInterface::class);

        return ['payment' => $paymentDO, 'amount' => $amount];
    }

    /**
     * @return RefundRequestInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    private function refundRow()
    {
        $refund = $this->createMock(RefundRequestInterface::class);
        $refund->method('getRequestId')->willReturn(self::REQUEST_ID);

        return $refund;
    }

    /**
     * @return array
     */
    private function builtRequest(): array
    {
        return [
            'partnerCode' => 'SECOMM',
            'orderId' => 'MOMO2609180000-SLP-1-RF1111',
            'requestId' => self::REQUEST_ID,
            'amount' => 150000,
        ];
    }

    /**
     * @return array
     */
    private function successResponse(): array
    {
        return [
            'partnerCode' => 'SECOMM',
            'orderId' => 'MOMO2609180000-SLP-1-RF1111',
            'requestId' => self::REQUEST_ID,
            'amount' => 150000,
            'transId' => 2820086739,
            'resultCode' => 0,
            'message' => ' Successful',
        ];
    }

    /**
     * @return void
     */
    public function testSuccessRunsHandlerAndReturnsResult(): void
    {
        $subject = $this->subject();
        $refund = $this->refundRow();
        $request = $this->builtRequest();
        $response = $this->successResponse();
        $result = $this->createMock(ArrayResult::class);

        $this->manager->method('openIdentity')->willReturn($refund);
        $this->requestBuilder->method('build')->willReturn($request);
        $this->transferFactory->expects($this->once())
            ->method('create')->with($request)
            ->willReturn($this->createMock(TransferInterface::class));
        $this->client->method('placeRequest')->willReturn($response);
        $this->classifier->expects($this->once())->method('classify')->willReturn(
            new RefundClassification(
                RefundRequestInterface::STATUS_SUCCESS,
                RefundRequestInterface::REASON_PROVIDER_CONFIRMED,
                '0',
                ' Successful',
                '2820086739'
            )
        );
        $this->manager->expects($this->once())->method('recordOutcome')->with($refund, $this->callback(
            function (RefundClassification $classification): bool {
                return $classification->isSuccess();
            }
        ));
        $this->handler->expects($this->once())->method('handle')->with($subject, $response);
        $this->resultFactory->expects($this->once())
            ->method('create')->with(['array' => $response])->willReturn($result);

        $this->assertSame($result, $this->command()->execute($subject));
    }

    /**
     * AC3: a blocked or duplicated refund never reaches the provider —
     * openIdentity throws before builder/HTTP are touched.
     *
     * @return void
     */
    public function testOpenIdentityBlockPreventsAnyProviderCall(): void
    {
        $this->manager->method('openIdentity')->willThrowException(
            new LocalizedException(__('A MoMo refund for this payment is already in progress.'))
        );
        $this->requestBuilder->expects($this->never())->method('build');
        $this->transferFactory->expects($this->never())->method('create');
        $this->client->expects($this->never())->method('placeRequest');
        $this->manager->expects($this->never())->method('recordOutcome');

        $this->expectException(LocalizedException::class);
        $this->command()->execute($this->subject());
    }

    /**
     * AC4: a provider refusal must not finalize accounting — the command
     * records the outcome and throws so the native creditmemo rolls back.
     *
     * @return void
     */
    public function testProviderRefusalRecordsFailedAndThrows(): void
    {
        $refund = $this->refundRow();
        $this->manager->method('openIdentity')->willReturn($refund);
        $this->requestBuilder->method('build')->willReturn($this->builtRequest());
        $this->transferFactory->method('create')
            ->willReturn($this->createMock(TransferInterface::class));
        $this->client->method('placeRequest')->willReturn(
            array_replace($this->successResponse(), ['resultCode' => 21, 'message' => ' Refused', 'transId' => 0])
        );
        $this->classifier->method('classify')->willReturn(
            new RefundClassification(
                RefundRequestInterface::STATUS_FAILED,
                RefundRequestInterface::REASON_PROVIDER_REFUSED,
                '21',
                ' Refused'
            )
        );
        $this->manager->expects($this->once())->method('recordOutcome')->with($refund, $this->callback(
            function (RefundClassification $classification): bool {
                return $classification->status === RefundRequestInterface::STATUS_FAILED;
            }
        ));
        $this->handler->expects($this->never())->method('handle');

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('MoMo refused the refund');
        $this->command()->execute($this->subject());
    }

    /**
     * AC5/AC6: an UNKNOWN outcome (processing, echo mismatch, malformed)
     * must not be retried blindly — the error carries the resolve command.
     *
     * @return void
     */
    public function testUnknownOutcomeThrowsWithResolveGuidance(): void
    {
        $refund = $this->refundRow();
        $this->manager->method('openIdentity')->willReturn($refund);
        $this->requestBuilder->method('build')->willReturn($this->builtRequest());
        $this->transferFactory->method('create')
            ->willReturn($this->createMock(TransferInterface::class));
        $this->client->method('placeRequest')->willReturn(['resultCode' => 7002]);
        $this->classifier->method('classify')->willReturn(
            new RefundClassification(
                RefundRequestInterface::STATUS_UNKNOWN,
                RefundRequestInterface::REASON_PROVIDER_PROCESSING,
                '7002'
            )
        );
        $this->manager->expects($this->once())->method('recordOutcome');
        $this->handler->expects($this->never())->method('handle');

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('momo:refund:resolve');
        $this->command()->execute($this->subject());
    }

    /**
     * AC5: timeout / transport failure is recorded as UNKNOWN (never
     * FAILED, never retried with a new identity) and surfaces the resolve
     * guidance.
     *
     * @return void
     */
    public function testTransportErrorRecordsUnknownWithoutNewIdentity(): void
    {
        $refund = $this->refundRow();
        $this->manager->method('openIdentity')->willReturn($refund);
        $this->requestBuilder->method('build')->willReturn($this->builtRequest());
        $this->transferFactory->method('create')
            ->willReturn($this->createMock(TransferInterface::class));
        $this->client->method('placeRequest')->willThrowException(
            new ClientException(__('Connection timed out'))
        );
        $this->classifier->expects($this->never())->method('classify');
        $this->manager->expects($this->once())->method('recordOutcome')->with(
            $refund,
            $this->callback(
                function (RefundClassification $classification): bool {
                    return $classification->status === RefundRequestInterface::STATUS_UNKNOWN
                        && $classification->reason === RefundRequestInterface::REASON_TRANSPORT_ERROR;
                }
            ),
            $this->isType('string')
        );
        $this->handler->expects($this->never())->method('handle');

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('could not be confirmed');
        $this->command()->execute($this->subject());
    }

    /**
     * An unreadable response body is treated exactly like a transport error.
     *
     * @return void
     */
    public function testConverterExceptionIsRecordedAsUnknown(): void
    {
        $refund = $this->refundRow();
        $this->manager->method('openIdentity')->willReturn($refund);
        $this->requestBuilder->method('build')->willReturn($this->builtRequest());
        $this->transferFactory->method('create')
            ->willReturn($this->createMock(TransferInterface::class));
        $this->client->method('placeRequest')->willThrowException(
            new ConverterException(__('Malformed JSON body'))
        );
        $this->manager->expects($this->once())->method('recordOutcome')->with(
            $refund,
            $this->isInstanceOf(RefundClassification::class),
            $this->isType('string')
        );

        $this->expectException(LocalizedException::class);
        $this->command()->execute($this->subject());
    }
}

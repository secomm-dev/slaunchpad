<?php
/**
 * Unit tests for momo:refund:resolve (MOMO-02).
 *
 * The resolve query is a DIFFERENT API operation than the refund
 * submission: it must mint a FRESH query requestId per invocation and sign
 * with it — never reuse the stored refund requestId (the provider
 * idempotency key of the refund itself) on the wire for another operation.
 * The classification contract tests live in RefundResultClassifierTest.
 *
 * @author    Secomm Teams
 * @copyright Copyright (c) 2026 Secomm (https://www.secomm.vn)
 * @package   Secomm_MoMo
 */
declare(strict_types=1);

namespace Secomm\MoMo\Test\Unit\Console\Command;

use Magento\Framework\App\State;
use Magento\Framework\Console\Cli;
use Magento\Framework\Phrase;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Payment\Gateway\Http\ClientException;
use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Http\TransferFactoryInterface;
use Magento\Payment\Gateway\Http\TransferInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\MoMo\Api\Data\RefundRequestInterface;
use Secomm\MoMo\Api\RefundRequestRepositoryInterface;
use Secomm\MoMo\Console\Command\RefundResolveCommand;
use Secomm\MoMo\Gateway\Helper\Signature;
use Secomm\MoMo\Model\OrderRefBuilder;
use Secomm\MoMo\Service\RefundClassification;
use Secomm\MoMo\Service\RefundRequestManager;
use Secomm\MoMo\Service\RefundResultClassifier;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;

class RefundResolveCommandTest extends TestCase
{
    private const REFUND_ORDER_ID = 'MOMO2609180000-SLP-1-RF1111';
    private const REFUND_REQUEST_ID = 'MOMO2609180000-SLP-1-RQ2222';

    /**
     * The query request on the wire carries a FRESH `-QQ` query requestId —
     * never the stored refund requestId — and the request identity is the
     * refund's own orderId.
     *
     * @return void
     */
    public function testQueryUsesFreshRequestIdentity(): void
    {
        $row = $this->row();
        $captured = null;
        $transferFactory = $this->createMock(TransferFactoryInterface::class);
        $transferFactory->method('create')->willReturnCallback(
            function (array $request) use (&$captured) {
                $captured = $request;
                return $this->createMock(TransferInterface::class);
            }
        );

        $command = $this->command($transferFactory, $this->client([]));
        $exit = $command->run(
            new StringInput(self::REFUND_REQUEST_ID),
            new BufferedOutput()
        );

        $this->assertSame(Cli::RETURN_SUCCESS, $exit);
        $this->assertNotNull($captured);
        $this->assertArrayHasKey('requestId', $captured);
        $this->assertNotSame(self::REFUND_REQUEST_ID, $captured['requestId']);
        $this->assertMatchesRegularExpression('/-QQ[0-9a-f]{4}$/', (string)$captured['requestId']);
        $this->assertSame(self::REFUND_ORDER_ID, $captured['orderId']);
    }

    /**
     * The signature on the wire covers EXACTLY the fresh query requestId
     * that was sent (verified by recomputing the HMAC with the same key
     * material and field order: accessKey, orderId, partnerCode, requestId).
     *
     * @return void
     */
    public function testSignatureCoversTheFreshQueryIdentity(): void
    {
        $captured = null;
        $transferFactory = $this->createMock(TransferFactoryInterface::class);
        $transferFactory->method('create')->willReturnCallback(
            function (array $request) use (&$captured) {
                $captured = $request;
                return $this->createMock(TransferInterface::class);
            }
        );

        $command = $this->command($transferFactory, $this->client([]));
        $command->run(
            new StringInput(self::REFUND_REQUEST_ID),
            new BufferedOutput()
        );

        $this->assertNotNull($captured);
        $expectedSignature = (new Signature())->sign(
            [
                'accessKey' => 'access-key',
                'orderId' => self::REFUND_ORDER_ID,
                'partnerCode' => 'SECOMM',
                'requestId' => $captured['requestId'],
            ],
            'secret-key'
        );
        $this->assertSame($expectedSignature, $captured['signature']);
    }

    /**
     * A terminal row is never re-queried — no provider call at all.
     *
     * @return void
     */
    public function testTerminalRowIsNeverQueried(): void
    {
        $transferFactory = $this->createMock(TransferFactoryInterface::class);
        $transferFactory->expects($this->never())->method('create');

        $command = $this->command($transferFactory, $this->client([]), $this->row(['isTerminal' => true]));
        $exit = $command->run(
            new StringInput(self::REFUND_REQUEST_ID),
            new BufferedOutput()
        );

        $this->assertSame(Cli::RETURN_SUCCESS, $exit);
    }

    /**
     * A transport failure keeps the row open and unknown: no outcome is
     * recorded (nothing becomes FAILED), and the command reports failure.
     *
     * @return void
     */
    public function testTransportFailureKeepsRowUnknown(): void
    {
        $transferFactory = $this->createMock(TransferFactoryInterface::class);
        $transferFactory->method('create')->willReturnCallback(
            fn () => $this->createMock(TransferInterface::class)
        );

        $manager = $this->createMock(RefundRequestManager::class);
        $manager->expects($this->never())->method('recordOutcome');

        $client = $this->createMock(ClientInterface::class);
        $client->method('placeRequest')->willThrowException(
            new ClientException(new Phrase('timeout'))
        );

        $command = $this->command($transferFactory, $client, $this->row(), $manager);
        $exit = $command->run(
            new StringInput(self::REFUND_REQUEST_ID),
            new BufferedOutput()
        );

        $this->assertSame(Cli::RETURN_FAILURE, $exit);
    }

    /**
     * A provider-confirmed SUCCESS classification is recorded on the row.
     *
     * @return void
     */
    public function testSuccessResolutionRecordsOutcome(): void
    {
        $transferFactory = $this->createMock(TransferFactoryInterface::class);
        $transferFactory->method('create')->willReturnCallback(
            fn () => $this->createMock(TransferInterface::class)
        );

        $classification = new RefundClassification(
            RefundRequestInterface::STATUS_SUCCESS,
            RefundRequestInterface::REASON_PROVIDER_CONFIRMED,
            '0',
            'Success',
            '2820086740'
        );
        $classifier = $this->createMock(RefundResultClassifier::class);
        $classifier->method('classifyQuery')->willReturn($classification);

        $manager = $this->createMock(RefundRequestManager::class);
        $manager->expects($this->once())->method('recordOutcome')->willReturn(true);

        $command = $this->command($transferFactory, $this->client([]), $this->row(), $manager, $classifier);
        $exit = $command->run(
            new StringInput(self::REFUND_REQUEST_ID),
            new BufferedOutput()
        );

        $this->assertSame(Cli::RETURN_SUCCESS, $exit);
    }

    /**
     * @param array $overrides
     * @return RefundRequestInterface&MockObject
     */
    private function row(array $overrides = []): RefundRequestInterface
    {
        $row = $this->createMock(RefundRequestInterface::class);
        $row->method('getEntityId')->willReturn(7);
        $row->method('getRequestId')->willReturn(self::REFUND_REQUEST_ID);
        $row->method('getRefundOrderId')->willReturn(self::REFUND_ORDER_ID);
        $row->method('getAmount')->willReturn(150000);
        $row->method('getStatus')->willReturn($overrides['status'] ?? RefundRequestInterface::STATUS_UNKNOWN);
        $row->method('getClassificationReason')->willReturn(RefundRequestInterface::REASON_TRANSPORT_ERROR);
        $row->method('isTerminal')->willReturn($overrides['isTerminal'] ?? false);

        return $row;
    }

    /**
     * @param array $response
     * @return ClientInterface&MockObject
     */
    private function client(array $response): ClientInterface
    {
        $client = $this->createMock(ClientInterface::class);
        $client->method('placeRequest')->willReturn($response);

        return $client;
    }

    /**
     * Build the command with a REAL OrderRefBuilder + Signature so the
     * minted `-QQ` identity and its HMAC are exercised for real.
     *
     * @param TransferFactoryInterface $transferFactory
     * @param ClientInterface $client
     * @param RefundRequestInterface|null $row
     * @param RefundRequestManager|null $manager
     * @param RefundResultClassifier|null $classifier
     * @return RefundResolveCommand
     */
    private function command(
        TransferFactoryInterface $transferFactory,
        ClientInterface $client,
        ?RefundRequestInterface $row = null,
        ?RefundRequestManager $manager = null,
        ?RefundResultClassifier $classifier = null
    ): RefundResolveCommand {
        $row ??= $this->row();

        $repository = $this->createMock(RefundRequestRepositoryInterface::class);
        $repository->method('getByRequestId')->willReturnCallback(
            fn (string $requestId): ?RefundRequestInterface => $requestId === self::REFUND_REQUEST_ID ? $row : null
        );

        $dateTime = $this->createMock(DateTime::class);
        $dateTime->method('gmtDate')->willReturn('2609180000');
        $orderRefBuilder = new OrderRefBuilder($dateTime);

        $config = $this->createMock(\Secomm\MoMo\Model\Config::class);
        $config->method('getPartnerCode')->willReturn('SECOMM');
        $config->method('getAccessKey')->willReturn('access-key');
        $config->method('getSecretKey')->willReturn('secret-key');

        $defaultClassifier = $this->createMock(RefundResultClassifier::class);
        $defaultClassifier->method('classifyQuery')->willReturn(
            new RefundClassification(
                RefundRequestInterface::STATUS_UNKNOWN,
                RefundRequestInterface::REASON_TRANSPORT_ERROR
            )
        );

        return new RefundResolveCommand(
            $repository,
            $manager ?? $this->createMock(RefundRequestManager::class),
            $classifier ?? $defaultClassifier,
            new Signature(),
            $config,
            $transferFactory,
            $client,
            $this->createMock(State::class),
            $orderRefBuilder
        );
    }
}

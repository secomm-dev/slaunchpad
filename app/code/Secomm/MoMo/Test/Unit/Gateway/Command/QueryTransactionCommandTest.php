<?php
/**
 * Unit test for the MoMo v2/query gateway command (MOMO-01).
 *
 * @author    Secomm Teams
 * @copyright Copyright (c) 2026 Secomm (https://www.secomm.vn)
 * @package   Secomm_MoMo
 */
declare(strict_types=1);

namespace Secomm\MoMo\Test\Unit\Gateway\Command;

use Magento\Payment\Gateway\Command\CommandException;
use Magento\Payment\Gateway\Command\Result\ArrayResult;
use Magento\Payment\Gateway\Command\Result\ArrayResultFactory;
use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Http\TransferFactoryInterface;
use Magento\Payment\Gateway\Http\TransferInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Payment\Gateway\Validator\ValidatorInterface;
use PHPUnit\Framework\TestCase;
use Secomm\MoMo\Gateway\Command\QueryTransactionCommand;

/**
 * The command carries the EXACT query request it just sent into the
 * validator subject (`query_request`), throws CommandException on an
 * invalid result (NO mutation, no result returned) and otherwise wraps the
 * raw response.
 */
class QueryTransactionCommandTest extends TestCase
{
    /**
     * @var BuilderInterface&\PHPUnit\Framework\MockObject\MockObject
     */
    private $requestBuilder;

    /**
     * @var TransferFactoryInterface&\PHPUnit\Framework\MockObject\MockObject
     */
    private $transferFactory;

    /**
     * @var ClientInterface&\PHPUnit\Framework\MockObject\MockObject
     */
    private $client;

    /**
     * @var ArrayResultFactory&\PHPUnit\Framework\MockObject\MockObject
     */
    private $resultFactory;

    /**
     * @var ValidatorInterface&\PHPUnit\Framework\MockObject\MockObject
     */
    private $validator;

    private QueryTransactionCommand $command;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->requestBuilder = $this->createMock(BuilderInterface::class);
        $this->transferFactory = $this->createMock(TransferFactoryInterface::class);
        $this->client = $this->createMock(ClientInterface::class);
        $this->resultFactory = $this->createMock(ArrayResultFactory::class);
        $this->validator = $this->createMock(ValidatorInterface::class);

        $this->command = new QueryTransactionCommand(
            $this->requestBuilder,
            $this->transferFactory,
            $this->client,
            $this->resultFactory,
            $this->validator
        );
    }

    /**
     * The validator subject carries the EXACT query request just sent
     * (`query_request`) plus the raw `response`, and the returned result
     * wraps the response.
     *
     * @return void
     */
    public function testValidatorSubjectCarriesExactQueryRequest(): void
    {
        $builtRequest = ['requestId' => 'MOMOREF-Qfreshest', 'orderId' => 'MOMOREF'];
        $response = ['resultCode' => 0, 'amount' => '150000', 'transId' => '987654321'];
        $subject = ['order_ref' => 'MOMOREF'];

        $this->requestBuilder->method('build')->willReturn($builtRequest);
        $transfer = $this->createMock(TransferInterface::class);
        $this->transferFactory->expects($this->once())
            ->method('create')->with($builtRequest)->willReturn($transfer);
        $this->client->expects($this->once())
            ->method('placeRequest')->with($transfer)->willReturn($response);

        $capturedSubject = null;
        $result = $this->createMock(\Magento\Payment\Gateway\Validator\ResultInterface::class);
        $result->method('isValid')->willReturn(true);
        $that = $this;
        $this->validator->method('validate')->willReturnCallback(
            function (array $validationSubject) use (&$capturedSubject, $result, $that) {
                $capturedSubject = $validationSubject;

                return $result;
            }
        );

        $arrayResult = $this->createMock(ArrayResult::class);
        $this->resultFactory->expects($this->once())
            ->method('create')->with(['array' => $response])->willReturn($arrayResult);

        $returned = $this->command->execute($subject);

        $this->assertSame($arrayResult, $returned);
        $this->assertSame($builtRequest, $capturedSubject['query_request'] ?? null);
        $this->assertSame($response, $capturedSubject['response'] ?? null);
        $this->assertSame('MOMOREF', $capturedSubject['order_ref'] ?? null);
    }

    /**
     * An invalid validator result throws CommandException and NO result is
     * handed to the caller (zero mutation downstream).
     *
     * @return void
     */
    public function testInvalidValidationThrowsCommandException(): void
    {
        $this->requestBuilder->method('build')->willReturn(['requestId' => 'MOMOREF-Qx']);
        $this->transferFactory->method('create')->willReturn(
            $this->createMock(TransferInterface::class)
        );
        $this->client->method('placeRequest')->willReturn(['resultCode' => 0]);

        $invalidResult = $this->createMock(\Magento\Payment\Gateway\Validator\ResultInterface::class);
        $invalidResult->method('isValid')->willReturn(false);
        $this->validator->method('validate')->willReturn($invalidResult);

        $this->resultFactory->expects($this->never())->method('create');

        $this->expectException(CommandException::class);
        $this->expectExceptionMessage('verification failed');

        $this->command->execute(['order_ref' => 'MOMOREF']);
    }
}

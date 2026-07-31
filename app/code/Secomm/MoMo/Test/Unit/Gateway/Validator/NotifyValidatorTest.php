<?php
/**
 * Unit test for the MoMo Notify (IPN) validator.
 *
 * @author    Secomm Teams
 * @copyright Copyright (c) 2024 Secomm (https://www.secomm.vn)
 * @package   Secomm_MoMo
 */
declare(strict_types=1);

namespace Secomm\MoMo\Test\Unit\Gateway\Validator;

use Magento\Payment\Gateway\Data\Order\OrderAdapter;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Validator\ResultInterface;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;
use PHPUnit\Framework\TestCase;
use Secomm\MoMo\Model\Config;
use Secomm\MoMo\Gateway\Helper\Signature;
use Secomm\MoMo\Gateway\Validator\NotifyValidator;

/**
 * Verifies the Notify validator accepts a correctly signed success result with a
 * matching amount, and rejects bad signatures, non-zero resultCodes, or amount
 * mismatches (spec §10: amount must be re-validated server-side).
 */
class NotifyValidatorTest extends TestCase
{
    /**
     * @var Config|\PHPUnit\Framework\MockObject\MockObject
     */
    private $config;

    /**
     * @var Signature
     */
    private Signature $signature;

    /**
     * @var ResultInterfaceFactory|\PHPUnit\Framework\MockObject\MockObject
     */
    private $resultFactory;

    /**
     * @var NotifyValidator
     */
    private NotifyValidator $validator;

    /**
     * @var OrderAdapter|\PHPUnit\Framework\MockObject\MockObject
     */
    private $orderAdapter;

    /**
     * @var bool|null
     */
    private ?bool $capturedIsValid = null;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->config = $this->createMock(Config::class);
        $this->config->method('getAccessKey')->willReturn('AK');
        $this->config->method('getSecretKey')->willReturn('SK');

        $this->signature = new Signature();

        $this->resultFactory = $this->createMock(ResultInterfaceFactory::class);
        $result = $this->createMock(ResultInterface::class);
        $that = $this;
        $this->resultFactory->method('create')
            ->willReturnCallback(function (array $args) use ($result, $that) {
                $that->capturedIsValid = $args['isValid'] ?? null;

                return $result;
            });

        // Order adapter exposes grand total + entity id — the values to compare against.
        $this->orderAdapter = $this->createMock(OrderAdapter::class);
        $this->orderAdapter->method('getGrandTotalAmount')->willReturn(1000.0);
        $this->orderAdapter->method('getId')->willReturn(123);

        $this->validator = new NotifyValidator($this->resultFactory, $this->config, $this->signature);
    }

    /**
     * Build a validation subject with a signed response and a payment DO whose
     * order grand total matches the response amount.
     *
     * @param array $response
     * @return array
     */
    private function subject(array $response): array
    {
        $paymentDO = $this->createMock(PaymentDataObjectInterface::class);
        $paymentDO->method('getOrder')->willReturn($this->orderAdapter);

        return ['response' => $response, 'payment' => $paymentDO];
    }

    /**
     * Build a MoMo result payload signed with the test secret.
     *
     * @param array $response
     * @return array
     */
    private function signed(array $response): array
    {
        $signedFields = [
            'accessKey', 'amount', 'extraData', 'message', 'orderId', 'orderInfo',
            'orderType', 'partnerCode', 'payType', 'requestId', 'responseTime',
            'resultCode', 'transId',
        ];
        $params = ['accessKey' => 'AK'];
        foreach ($signedFields as $field) {
            if ($field === 'accessKey') {
                continue;
            }
            $params[$field] = (string)($response[$field] ?? '');
        }
        $response['signature'] = $this->signature->sign($params, 'SK');

        return $response;
    }

    /**
     * Correctly signed success result with matching amount is valid.
     *
     * @return void
     */
    public function testValidSignedSuccessResultPasses(): void
    {
        $response = $this->signed([
            'partnerCode' => 'MOMO',
            'orderId' => 'ORD-1',
            'requestId' => 'R-1',
            'amount' => 1000,
            'transId' => 'T-1',
            'resultCode' => 0,
            'message' => 'Successful',
            'responseTime' => 1700000000000,
            'extraData' => '',
            'orderInfo' => 'info',
            'orderType' => 'momo_wallet',
            'payType' => 'creditApp',
        ]);

        $this->validator->validate($this->subject($response));

        $this->assertTrue($this->capturedIsValid);
    }

    /**
     * Tampered signature is rejected.
     *
     * @return void
     */
    public function testTamperedSignatureFails(): void
    {
        $response = $this->signed(['orderId' => 'ORD-1', 'amount' => 1000, 'transId' => 'T-1', 'resultCode' => 0]);
        $response['signature'] = 'tampered';

        $this->validator->validate($this->subject($response));

        $this->assertFalse($this->capturedIsValid);
    }

    /**
     * Non-zero resultCode is rejected even with a valid signature.
     *
     * @return void
     */
    public function testNonZeroResultCodeFails(): void
    {
        $response = $this->signed(['orderId' => 'ORD-1', 'amount' => 1000, 'transId' => 'T-1', 'resultCode' => 700]);

        $this->validator->validate($this->subject($response));

        $this->assertFalse($this->capturedIsValid);
    }

    /**
     * Amount mismatch (signed 500 but order total is 1000) is rejected — spec §10.
     *
     * @return void
     */
    public function testAmountMismatchFails(): void
    {
        $response = $this->signed([
            'orderId' => 'ORD-1',
            'amount' => 500, // mismatched — order grand total is 1000.
            'transId' => 'T-1',
            'resultCode' => 0,
        ]);

        $this->validator->validate($this->subject($response));

        $this->assertFalse($this->capturedIsValid);
    }

    /**
     * extraData that does not map to the order entity id is rejected — fetch-to-confirm.
     *
     * @return void
     */
    public function testExtraDataMismatchFails(): void
    {
        $response = $this->signed([
            'orderId' => 'ORD-1',
            'amount' => 1000,
            'transId' => 'T-1',
            'resultCode' => 0,
            'extraData' => base64_encode('999'), // wrong entity id
        ]);

        $this->validator->validate($this->subject($response));

        $this->assertFalse($this->capturedIsValid);
    }
}

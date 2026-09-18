<?php
/**
 * Unit test for the MoMo v2/query response validator (MOMO-01).
 *
 * @author    Secomm Teams
 * @copyright Copyright (c) 2026 Secomm (https://www.secomm.vn)
 * @package   Secomm_MoMo
 */
declare(strict_types=1);

namespace Secomm\MoMo\Test\Unit\Gateway\Validator;

use Magento\Payment\Gateway\Validator\ResultInterface;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;
use PHPUnit\Framework\TestCase;
use Secomm\MoMo\Gateway\Helper\Signature;
use Secomm\MoMo\Gateway\Validator\QueryValidator;
use Secomm\MoMo\Model\Config;
use Secomm\MoMo\Model\PaymentAttempt;

/**
 * Verifies the query response chain: signature over MoMo's query field
 * order, merchant identity and the orderId/requestId echoes against the
 * attempt. Business mapping (resultCode/amount/transId) is the caller's.
 */
class QueryValidatorTest extends TestCase
{
    private const ORDER_REF = 'MOMO260918120000200000001ab12';
    private const REQUEST_ID = 'MOMO260918120000200000001ab12-Rcd34';

    /**
     * @var Config&\PHPUnit\Framework\MockObject\MockObject
     */
    private $config;

    private Signature $signature;

    /**
     * @var ResultInterfaceFactory&\PHPUnit\Framework\MockObject\MockObject
     */
    private $resultFactory;

    private QueryValidator $validator;

    private ?bool $capturedIsValid = null;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->config = $this->createMock(Config::class);
        $this->config->method('getAccessKey')->willReturn('AK');
        $this->config->method('getSecretKey')->willReturn('SK');
        $this->config->method('getPartnerCode')->willReturn('MOMO');

        $this->signature = new Signature();

        $this->resultFactory = $this->createMock(ResultInterfaceFactory::class);
        $result = $this->createMock(ResultInterface::class);
        $that = $this;
        $this->resultFactory->method('create')
            ->willReturnCallback(function (array $args) use ($result, $that) {
                $that->capturedIsValid = $args['isValid'] ?? null;

                return $result;
            });

        $this->validator = new QueryValidator($this->resultFactory, $this->config, $this->signature);
    }

    /**
     * A correctly signed paid query response validates.
     *
     * @return void
     */
    public function testValidSignedPaidResponsePasses(): void
    {
        $this->validator->validate(
            ['response' => $this->signed($this->basePayload(0)), 'attempt' => $this->attempt()]
        );

        $this->assertTrue($this->capturedIsValid);
    }

    /**
     * A still-processing response (7002) also validates: the validator is
     * identity + integrity only — the caller decides 7002 is non-terminal.
     *
     * @return void
     */
    public function testProcessingResponseAlsoPasses(): void
    {
        $this->validator->validate(
            ['response' => $this->signed($this->basePayload(7002)), 'attempt' => $this->attempt()]
        );

        $this->assertTrue($this->capturedIsValid);
    }

    /**
     * A tampered signature is rejected — the response can never drive any
     * state decision.
     *
     * @return void
     */
    public function testTamperedSignatureFails(): void
    {
        $response = $this->signed($this->basePayload(0));
        $response['signature'] = 'tampered';
        $this->validator->validate(['response' => $response, 'attempt' => $this->attempt()]);

        $this->assertFalse($this->capturedIsValid);
    }

    /**
     * A response for a different merchant identity is rejected.
     *
     * @return void
     */
    public function testPartnerCodeMismatchFails(): void
    {
        $payload = $this->basePayload(0);
        $payload['partnerCode'] = 'FOREIGN';
        $this->validator->validate(['response' => $this->signed($payload), 'attempt' => $this->attempt()]);

        $this->assertFalse($this->capturedIsValid);
    }

    /**
     * An orderId not matching the attempt order_ref is rejected.
     *
     * @return void
     */
    public function testOrderIdMismatchFails(): void
    {
        $payload = $this->basePayload(0);
        $payload['orderId'] = 'MOMOOTHERREF';
        $this->validator->validate(['response' => $this->signed($payload), 'attempt' => $this->attempt()]);

        $this->assertFalse($this->capturedIsValid);
    }

    /**
     * A requestId not matching the attempt is rejected.
     *
     * @return void
     */
    public function testRequestIdMismatchFails(): void
    {
        $payload = $this->basePayload(0);
        $payload['requestId'] = 'FOREIGN-REQUEST';
        $this->validator->validate(['response' => $this->signed($payload), 'attempt' => $this->attempt()]);

        $this->assertFalse($this->capturedIsValid);
    }

    /**
     * Validation without a resolved attempt is refused outright.
     *
     * @return void
     */
    public function testMissingAttemptFails(): void
    {
        $this->validator->validate(['response' => $this->signed($this->basePayload(0)), 'attempt' => null]);

        $this->assertFalse($this->capturedIsValid);
    }

    /**
     * @param int $resultCode
     * @return array The canonical query response payload.
     */
    private function basePayload(int $resultCode): array
    {
        return [
            'partnerCode' => 'MOMO',
            'orderId' => self::ORDER_REF,
            'requestId' => self::REQUEST_ID,
            'amount' => 150000,
            'transId' => 987654321,
            'resultCode' => $resultCode,
            'message' => $resultCode === 0 ? 'Successful' : 'Transaction is processing',
            'responseTime' => 1787000000000,
        ];
    }

    /**
     * Sign the payload over MoMo's query-response field order.
     *
     * @param array $response
     * @return array
     */
    private function signed(array $response): array
    {
        $signedFields = ['accessKey', 'amount', 'message', 'orderId', 'partnerCode', 'responseTime'];
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
     * The persisted attempt the response must echo.
     *
     * @return PaymentAttempt
     */
    private function attempt(): PaymentAttempt
    {
        $attempt = new PaymentAttempt(
            $this->createMock(\Magento\Framework\Model\Context::class),
            $this->createMock(\Magento\Framework\Registry::class)
        );
        $attempt->setOrderRef(self::ORDER_REF);
        $attempt->setRequestId(self::REQUEST_ID);
        $attempt->setAmount(150000);

        return $attempt;
    }
}

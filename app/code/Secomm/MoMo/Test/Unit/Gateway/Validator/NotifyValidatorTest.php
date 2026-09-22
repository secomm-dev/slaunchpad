<?php
/**
 * Unit test for the attempt-based MoMo Notify (IPN) validator (MOMO-01).
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
use Secomm\MoMo\Gateway\Validator\NotifyValidator;
use Secomm\MoMo\Model\Config;
use Secomm\MoMo\Model\PaymentAttempt;

/**
 * Verifies the IPN authoritative chain: signature over the 13 MoMo-signed
 * fields, merchant identity (partnerCode), transaction identity echoes
 * (orderId/requestId/extraData vs the attempt) and the frozen amount. The
 * business outcome (resultCode) is deliberately NOT the validator's
 * concern — a signature-valid failure must validate.
 */
class NotifyValidatorTest extends TestCase
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

    private NotifyValidator $validator;

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

        $this->validator = new NotifyValidator($this->resultFactory, $this->config, $this->signature);
    }

    /**
     * A correctly signed success payload with matching identity echoes and
     * frozen amount validates.
     *
     * @return void
     */
    public function testValidSignedSuccessPasses(): void
    {
        $response = $this->signed($this->basePayload());
        $this->validator->validate(['response' => $response, 'attempt' => $this->attempt()]);

        $this->assertTrue($this->capturedIsValid);
    }

    /**
     * A signature-valid authoritative FAILURE also validates: identity +
     * integrity are the validator's scope; the resultCode decision is the
     * caller's (IpnProcessor records the verified failure).
     *
     * @return void
     */
    public function testSignatureValidFailureAlsoPasses(): void
    {
        $payload = $this->basePayload();
        $payload['resultCode'] = 700;
        $payload['message'] = 'User cancelled.';
        $response = $this->signed($payload);
        $this->validator->validate(['response' => $response, 'attempt' => $this->attempt()]);

        $this->assertTrue($this->capturedIsValid);
    }

    /**
     * A tampered signature is rejected.
     *
     * @return void
     */
    public function testTamperedSignatureFails(): void
    {
        $response = $this->signed($this->basePayload());
        $response['signature'] = 'tampered';
        $this->validator->validate(['response' => $response, 'attempt' => $this->attempt()]);

        $this->assertFalse($this->capturedIsValid);
    }

    /**
     * A payload from a different merchant (partnerCode) is rejected even
     * when the signature arithmetic would pass with a foreign key.
     *
     * @return void
     */
    public function testPartnerCodeMismatchFails(): void
    {
        $payload = $this->basePayload();
        $payload['partnerCode'] = 'FOREIGN';
        $response = $this->signed($payload);
        $this->validator->validate(['response' => $response, 'attempt' => $this->attempt()]);

        $this->assertFalse($this->capturedIsValid);
    }

    /**
     * An orderId that is not this attempt's order_ref is rejected.
     *
     * @return void
     */
    public function testOrderIdMismatchFails(): void
    {
        $payload = $this->basePayload();
        $payload['orderId'] = 'MOMOOTHERREF';
        $response = $this->signed($payload);
        $this->validator->validate(['response' => $response, 'attempt' => $this->attempt()]);

        $this->assertFalse($this->capturedIsValid);
    }

    /**
     * A requestId that is not this attempt's requestId is rejected.
     *
     * @return void
     */
    public function testRequestIdMismatchFails(): void
    {
        $payload = $this->basePayload();
        $payload['requestId'] = 'FOREIGN-REQUEST';
        $response = $this->signed($payload);
        $this->validator->validate(['response' => $response, 'attempt' => $this->attempt()]);

        $this->assertFalse($this->capturedIsValid);
    }

    /**
     * extraData that does not decode to the attempt order_ref is rejected.
     *
     * @return void
     */
    public function testExtraDataMismatchFails(): void
    {
        $payload = $this->basePayload();
        $payload['extraData'] = base64_encode('SOMEOTHERREF');
        $response = $this->signed($payload);
        $this->validator->validate(['response' => $response, 'attempt' => $this->attempt()]);

        $this->assertFalse($this->capturedIsValid);
    }

    /**
     * An amount different from the frozen attempt amount is rejected —
     * spec §10 / AC4.
     *
     * @return void
     */
    public function testAmountMismatchFails(): void
    {
        $payload = $this->basePayload();
        $payload['amount'] = 500;
        $response = $this->signed($payload);
        $this->validator->validate(['response' => $response, 'attempt' => $this->attempt()]);

        $this->assertFalse($this->capturedIsValid);
    }

    /**
     * A missing amount is rejected (never defaults to a passing zero).
     *
     * @return void
     */
    public function testMissingAmountFails(): void
    {
        $payload = $this->basePayload();
        unset($payload['amount']);
        $response = $this->signed($payload);
        $this->validator->validate(['response' => $response, 'attempt' => $this->attempt()]);

        $this->assertFalse($this->capturedIsValid);
    }

    /**
     * Validation without a resolved attempt is refused outright.
     *
     * @return void
     */
    public function testMissingAttemptFails(): void
    {
        $this->validator->validate(['response' => $this->signed($this->basePayload()), 'attempt' => null]);

        $this->assertFalse($this->capturedIsValid);
    }

    /**
     * @return array The canonical success payload for the test attempt.
     */
    private function basePayload(): array
    {
        return [
            'partnerCode' => 'MOMO',
            'orderId' => self::ORDER_REF,
            'requestId' => self::REQUEST_ID,
            'amount' => 150000,
            'transId' => '987654321',
            'resultCode' => 0,
            'message' => 'Successful',
            'responseTime' => 1787000000000,
            'extraData' => base64_encode(self::ORDER_REF),
            'orderInfo' => 'Pay for order',
            'orderType' => 'momo_wallet',
            'payType' => 'webApp',
        ];
    }

    /**
     * Sign the payload over MoMo's fixed 13-field order with the test secret.
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
     * The persisted attempt the payload must echo.
     *
     * @return PaymentAttempt
     */
    private function attempt(): PaymentAttempt
    {
        $attempt = new PaymentAttempt($this->createMock(\Magento\Framework\Model\Context::class), $this->createMock(\Magento\Framework\Registry::class));
        $attempt->setOrderRef(self::ORDER_REF);
        $attempt->setRequestId(self::REQUEST_ID);
        $attempt->setAmount(150000);

        return $attempt;
    }
}

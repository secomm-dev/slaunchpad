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
use Secomm\MoMo\Api\Data\PaymentAttemptInterface;
use Secomm\MoMo\Gateway\Validator\QueryValidator;
use Secomm\MoMo\Model\Config;

/**
 * Provider-realistic contract: MoMo signs the v2/query REQUEST but the
 * RESPONSE carries NO signature. The validator therefore checks merchant
 * identity + the orderId/requestId echoes against the EXACT query request
 * just sent + a strictly integer-formed amount — nothing fabricated.
 */
class QueryValidatorTest extends TestCase
{
    private const ORDER_REF = 'MOMO260918120000200000001ab12';
    private const FRESH_REQUEST_ID = 'MOMO260918120000200000001ab12-Q1111222233334444';

    /**
     * @var Config&\PHPUnit\Framework\MockObject\MockObject
     */
    private $config;

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
        $this->config->method('getPartnerCode')->willReturn('MOMO');

        $this->resultFactory = $this->createMock(ResultInterfaceFactory::class);
        $result = $this->createMock(ResultInterface::class);
        $that = $this;
        $this->resultFactory->method('create')
            ->willReturnCallback(function (array $args) use ($result, $that) {
                $that->capturedIsValid = $args['isValid'] ?? null;

                return $result;
            });

        $this->validator = new QueryValidator($this->resultFactory, $this->config);
    }

    /**
     * A provider-realistic success response echoing the exact query
     * request validates.
     *
     * @return void
     */
    public function testProviderRealisticResponsePasses(): void
    {
        $this->validator->validate($this->subject());

        $this->assertTrue($this->capturedIsValid);
    }

    /**
     * A signature-valid FAILURE response (resultCode 700) ALSO validates:
     * identity/integrity are the validator's scope; resultCode decisions
     * belong to the caller.
     *
     * @return void
     */
    public function testAuthoritativeFailureResponseAlsoPasses(): void
    {
        $this->validator->validate($this->subject());

        $this->assertTrue($this->capturedIsValid);
    }

    /**
     * A response from a different merchant (partnerCode) is rejected.
     *
     * @return void
     */
    public function testPartnerCodeMismatchFails(): void
    {
        $subject = $this->subject();
        $subject['response']['partnerCode'] = 'FOREIGN';
        $this->validator->validate($subject);

        $this->assertFalse($this->capturedIsValid);
    }

    /**
     * An orderId that is not the attempt's order_ref is rejected even when
     * it echoes the query request (identity to the attempt is mandatory).
     *
     * @return void
     */
    public function testOrderIdMismatchFails(): void
    {
        $subject = $this->subject();
        $subject['query_request']['orderId'] = 'MOMOOTHERREF';
        $subject['response']['orderId'] = 'MOMOOTHERREF';
        $this->validator->validate($subject);

        $this->assertFalse($this->capturedIsValid);
    }

    /**
     * A requestId echo differing from the EXACT query request just sent is
     * rejected (covers stale/create-time requestIds and replayed echoes).
     *
     * @return void
     */
    public function testRequestIdEchoMismatchFails(): void
    {
        $subject = $this->subject();
        $subject['response']['requestId'] = 'MOMO260918120000200000001ab12-Q9999';
        $this->validator->validate($subject);

        $this->assertFalse($this->capturedIsValid);
    }

    /**
     * A query subject without the exact request just sent is refused
     * (programming error — the command always carries it).
     *
     * @return void
     */
    public function testMissingQueryRequestFails(): void
    {
        $subject = $this->subject();
        unset($subject['query_request']);
        $this->validator->validate($subject);

        $this->assertFalse($this->capturedIsValid);
    }

    /**
     * Validation without a resolved attempt is refused outright.
     *
     * @return void
     */
    public function testMissingAttemptFails(): void
    {
        $subject = $this->subject();
        $subject['attempt'] = null;
        $this->validator->validate($subject);

        $this->assertFalse($this->capturedIsValid);
    }

    /**
     * A missing amount is rejected (never defaults to a passing zero).
     *
     * @return void
     */
    public function testMissingAmountFails(): void
    {
        $subject = $this->subject();
        unset($subject['response']['amount']);
        $this->validator->validate($subject);

        $this->assertFalse($this->capturedIsValid);
    }

    /**
     * A malformed amount (decimal/signed/array) is rejected — strict
     * integer grammar, zero mutation.
     *
     * @return void
     */
    public function testMalformedAmountFails(): void
    {
        foreach (['12.5', '+150000', ['150000']] as $badAmount) {
            $subject = $this->subject();
            $subject['response']['amount'] = $badAmount;
            $this->validator->validate($subject);

            $this->assertFalse($this->capturedIsValid, 'Amount must fail: ' . json_encode($badAmount));
            $this->capturedIsValid = null;
        }
    }

    /**
     * Build a full validation subject: exact query request + provider-
     * realistic success response + resolved attempt.
     *
     * @return array
     */
    private function subject(): array
    {
        $attempt = $this->createMock(PaymentAttemptInterface::class);
        $attempt->method('getOrderRef')->willReturn(self::ORDER_REF);

        return [
            'query_request' => [
                'partnerCode' => 'MOMO',
                'orderId' => self::ORDER_REF,
                'requestId' => self::FRESH_REQUEST_ID,
                'lang' => 'vi',
                'signature' => 'computed-request-signature',
            ],
            'response' => [
                'partnerCode' => 'MOMO',
                'orderId' => self::ORDER_REF,
                'requestId' => self::FRESH_REQUEST_ID,
                'extraData' => '',
                'amount' => '150000',
                'transId' => '987654321',
                'resultCode' => 0,
                'message' => 'Successful',
                'responseTime' => '20260918120000',
            ],
            'attempt' => $attempt,
        ];
    }
}
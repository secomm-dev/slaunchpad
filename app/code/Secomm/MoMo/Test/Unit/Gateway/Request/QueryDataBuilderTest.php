<?php
/**
 * Unit test for the MoMo v2/query request builder (MOMO-01).
 *
 * @author    Secomm Teams
 * @copyright Copyright (c) 2026 Secomm (https://www.secomm.vn)
 * @package   Secomm_MoMo
 */
declare(strict_types=1);

namespace Secomm\MoMo\Test\Unit\Gateway\Request;

use PHPUnit\Framework\TestCase;
use Secomm\MoMo\Gateway\Helper\Signature;
use Secomm\MoMo\Gateway\Request\QueryDataBuilder;
use Secomm\MoMo\Model\Config;

/**
 * The v2/query requestId is minted FRESH per invocation (never the
 * attempt's persisted create-time request_id) and the whole request is
 * signed over accessKey&orderId&partnerCode&requestId — verifiable here
 * against the REAL Signature helper.
 */
class QueryDataBuilderTest extends TestCase
{
    private const ORDER_REF = 'MOMO260918120000200000001ab12';

    /**
     * @var Config&\PHPUnit\Framework\MockObject\MockObject
     */
    private $config;

    private QueryDataBuilder $builder;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->config = $this->createMock(Config::class);
        $this->config->method('getPartnerCode')->willReturn('MOMO');
        $this->config->method('getAccessKey')->willReturn('AK001');
        $this->config->method('getSecretKey')->willReturn('unit-test-secret');

        $this->builder = new QueryDataBuilder($this->config, new Signature());
    }

    /**
     * Each build mints a fresh requestId scoped to the order_ref; two
     * invocations NEVER share one.
     *
     * @return void
     */
    public function testFreshUniqueRequestIdPerBuild(): void
    {
        $first = $this->builder->build(['order_ref' => self::ORDER_REF]);
        $second = $this->builder->build(['order_ref' => self::ORDER_REF]);

        $this->assertNotSame($first['requestId'], $second['requestId']);
        $this->assertStringStartsWith(self::ORDER_REF . '-Q', $first['requestId']);
        $this->assertStringStartsWith(self::ORDER_REF . '-Q', $second['requestId']);
    }

    /**
     * The orderId carried is exactly the attempt's order_ref.
     *
     * @return void
     */
    public function testOrderIdIsTheOrderRef(): void
    {
        $request = $this->builder->build(['order_ref' => self::ORDER_REF]);

        $this->assertSame(self::ORDER_REF, $request['orderId']);
        $this->assertSame('MOMO', $request['partnerCode']);
        $this->assertSame('vi', $request['lang']);
    }

    /**
     * The signature verifies over accessKey&orderId&partnerCode&requestId
     * with the minted requestId — proving the signed request binds the
     * fresh requestId.
     *
     * @return void
     */
    public function testSignatureVerifiesOverMintedRequest(): void
    {
        $request = $this->builder->build(['order_ref' => self::ORDER_REF]);

        $verified = (new Signature())->verify(
            (string)$request['signature'],
            [
                'accessKey' => 'AK001',
                'orderId' => self::ORDER_REF,
                'partnerCode' => 'MOMO',
                'requestId' => $request['requestId'],
            ],
            'unit-test-secret'
        );

        $this->assertTrue($verified);
    }

    /**
     * A signature computed for a DIFFERENT requestId does not verify over
     * the minted one (the signature binds the exact request).
     *
     * @return void
     */
    public function testSignatureBindsTheExactRequestId(): void
    {
        $request = $this->builder->build(['order_ref' => self::ORDER_REF]);

        $verified = (new Signature())->verify(
            (string)$request['signature'],
            [
                'accessKey' => 'AK001',
                'orderId' => self::ORDER_REF,
                'partnerCode' => 'MOMO',
                'requestId' => self::ORDER_REF . '-Qstalevalue00000000000000',
            ],
            'unit-test-secret'
        );

        $this->assertFalse($verified);
    }

    /**
     * A build without order_ref is refused outright.
     *
     * @return void
     */
    public function testMissingOrderRefThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('order_ref should be provided');

        $this->builder->build([]);
    }
}

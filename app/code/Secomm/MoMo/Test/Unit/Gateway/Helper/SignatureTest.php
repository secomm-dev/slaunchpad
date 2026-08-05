<?php
/**
 * Unit test for the MoMo signature helper.
 *
 * @author    Secomm Teams
 * @copyright Copyright (c) 2024 Secomm (https://www.secomm.vn)
 * @package   Secomm_MoMo
 */
declare(strict_types=1);

namespace Secomm\MoMo\Test\Unit\Gateway\Helper;

use PHPUnit\Framework\TestCase;
use Secomm\MoMo\Gateway\Helper\Signature;

/**
 * Verifies rawSignature ordering, HMAC signing and verification.
 */
class SignatureTest extends TestCase
{
    /**
     * @var Signature
     */
    private Signature $signature;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->signature = new Signature();
    }

    /**
     * rawSignature joins ordered key=value pairs with &.
     *
     * @return void
     */
    public function testBuildRawSignaturePreservesOrder(): void
    {
        $params = ['b' => 2, 'a' => 1, 'c' => 3];

        $this->assertSame('b=2&a=1&c=3', $this->signature->buildRawSignature($params));
    }

    /**
     * sign() produces the MoMo HMAC-SHA256 of the rawSignature with the secret key.
     *
     * @return void
     */
    public function testSignProducesHmacSha256(): void
    {
        $params = ['accessKey' => 'AK', 'amount' => 1000, 'orderId' => 'ORD-1'];
        $secret = 'SK';

        // Independently computed expected value.
        $raw = 'accessKey=AK&amount=1000&orderId=ORD-1';
        $expected = hash_hmac('sha256', $raw, $secret);

        $this->assertSame($expected, $this->signature->sign($params, $secret));
    }

    /**
     * verify() accepts a correct signature and rejects a wrong one.
     *
     * @return void
     */
    public function testVerifyAcceptsCorrectAndRejectsWrong(): void
    {
        $params = ['accessKey' => 'AK', 'amount' => 1000];
        $secret = 'SK';
        $valid = $this->signature->sign($params, $secret);

        $this->assertTrue($this->signature->verify($valid, $params, $secret));
        $this->assertFalse($this->signature->verify('tampered', $params, $secret));
    }

    /**
     * Field order matters: same values, different order -> different signature.
     *
     * @return void
     */
    public function testFieldOrderChangesSignature(): void
    {
        $secret = 'SK';
        $a = ['x' => 1, 'y' => 2];
        $b = ['y' => 2, 'x' => 1];

        $this->assertNotEquals($this->signature->sign($a, $secret), $this->signature->sign($b, $secret));
    }
}

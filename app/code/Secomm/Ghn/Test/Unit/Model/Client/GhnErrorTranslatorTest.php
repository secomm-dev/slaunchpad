<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Test\Unit\Model\Client;

use PHPUnit\Framework\TestCase;
use Secomm\Ghn\Api\Exception\ProviderAuthenticationException;
use Secomm\Ghn\Api\Exception\ProviderInvalidAddressException;
use Secomm\Ghn\Api\Exception\ProviderInvalidRequestException;
use Secomm\Ghn\Api\Exception\ProviderRateLimitException;
use Secomm\Ghn\Api\Exception\ProviderRateUnavailableException;
use Secomm\Ghn\Api\Exception\ProviderRemoteException;
use Secomm\Ghn\Api\Exception\ProviderServiceUnavailableException;
use Secomm\Ghn\Model\Client\GhnErrorTranslator;

/**
 * TASK-RJFTPZ / AC-A3 — deterministic GHN error → typed provider exception mapping (SPEC §11).
 * Address- and rate-shaped messages take precedence over the numeric code buckets.
 */
class GhnErrorTranslatorTest extends TestCase
{
    private GhnErrorTranslator $translator;

    protected function setUp(): void
    {
        $this->translator = new GhnErrorTranslator();
    }

    /**
     * @return array<string, array{0: int, 1: string, 2: class-string}>
     */
    public static function codeProvider(): array
    {
        return [
            '401 authenticates' => [401, 'token not found', ProviderAuthenticationException::class],
            '402 authenticates' => [402, 'invalid token', ProviderAuthenticationException::class],
            '403 authenticates' => [403, 'forbidden', ProviderAuthenticationException::class],
            '400 invalid request' => [400, 'code is required', ProviderInvalidRequestException::class],
            '404 route not supported' => [404, 'not found', ProviderInvalidRequestException::class],
            '429 rate limited' => [429, 'too many requests', ProviderRateLimitException::class],
            '500 remote outage' => [500, 'internal error', ProviderServiceUnavailableException::class],
            '503 remote outage' => [503, 'service unavailable', ProviderServiceUnavailableException::class],
            'unmapped code stays remote' => [418, 'i am a teapot', ProviderRemoteException::class],
        ];
    }

    /**
     * @dataProvider codeProvider
     */
    public function testCodeBuckets(int $code, string $message, string $expectedException): void
    {
        $exception = $this->translator->translate($code, $message, 'test_op');

        $this->assertInstanceOf($expectedException, $exception);
        $this->assertStringContainsString('test_op', $exception->getMessage());
        $this->assertStringContainsString((string) $code, $exception->getMessage());
    }

    public function testAddressShapedMessageWinsOverCodeBucket(): void
    {
        $exception = $this->translator->translate(400, 'WARD_IS_INVALID: new-format address could not be mapped', 'calculate_fee');

        $this->assertInstanceOf(ProviderInvalidAddressException::class, $exception);
    }

    public function testRateUnavailableMessageWinsOverCodeBucket(): void
    {
        // Contains DISTRICT too — the "no service for route" business answer must classify as
        // rate unavailable, not as an address defect.
        $exception = $this->translator->translate(400, 'Service is not ready or not available for this district', 'calculate_fee');

        $this->assertInstanceOf(ProviderRateUnavailableException::class, $exception);
    }

    public function testDistrictShapedMessageClassifiesAsAddress(): void
    {
        $exception = $this->translator->translate(400, 'DISTRICT_NOT_FOUND', 'available_services');

        $this->assertInstanceOf(ProviderInvalidAddressException::class, $exception);
    }
}

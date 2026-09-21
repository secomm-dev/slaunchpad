<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Test\Unit\Model\Logger;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Secomm\Ghn\Model\Logger\GhnLogger;

/**
 * TASK-RJFTPZ / AC-A4 — token hygiene: no sensitive value may reach the writer, whatever the log
 * level or nesting. shop_id itself STAYS in the log (SPEC §48 asks for it) — only header-shaped
 * or token-shaped keys are scrubbed.
 */
class TokenScrubTest extends TestCase
{
    public function testSensitiveKeysAreScrubbed(): void
    {
        $context = [
            'token' => 'secret-token',
            'api_token' => 'secret-token',
            'ShopId_header' => '999111',
            'authorization' => 'Bearer something',
        ];

        $clean = GhnLogger::sanitizeContext($context);

        $this->assertSame(
            [
                'token' => GhnLogger::SCRUBBED,
                'api_token' => GhnLogger::SCRUBBED,
                'ShopId_header' => GhnLogger::SCRUBBED,
                'authorization' => GhnLogger::SCRUBBED,
            ],
            $clean
        );
        $this->assertStringNotContainsString('secret-token', json_encode($clean, JSON_THROW_ON_ERROR));
    }

    public function testShopIdAndOperationalContextSurvive(): void
    {
        $context = [
            'operation' => 'master_data_provinces',
            'shop_id' => '999111',
            'http_status' => 200,
            'provider_code' => 200,
            'duration_ms' => 87,
        ];

        $clean = GhnLogger::sanitizeContext($context);

        $this->assertSame($context, $clean);
    }

    public function testNestedPayloadIsScrubbedRecursively(): void
    {
        $context = [
            'operation' => 'create_order',
            'request' => [
                'client_order_code' => 'GHNS-1',
                'Token' => 'leaky',
            ],
        ];

        $clean = GhnLogger::sanitizeContext($context);

        $this->assertSame(GhnLogger::SCRUBBED, $clean['request']['Token']);
        $this->assertSame('GHNS-1', $clean['request']['client_order_code']);
    }

    public function testCallWritesSanitizedContext(): void
    {
        /** @var LoggerInterface&MockObject $psrLogger */
        $psrLogger = $this->createMock(LoggerInterface::class);
        $psrLogger->expects($this->once())
            ->method('info')
            ->with(
                'GHN call',
                $this->callback(static function (array $context): bool {
                    return ($context['api_token'] ?? null) === GhnLogger::SCRUBBED
                        && $context['operation'] === 'calculate_fee';
                })
            );

        (new GhnLogger($psrLogger))->call('GHN call', [
            'operation' => 'calculate_fee',
            'api_token' => 'secret-token',
        ]);
    }
}

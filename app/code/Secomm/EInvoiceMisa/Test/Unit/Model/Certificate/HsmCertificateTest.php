<?php

declare(strict_types=1);

namespace Secomm\EInvoiceMisa\Test\Unit\Model\Certificate;

use PHPUnit\Framework\TestCase;
use Secomm\EInvoiceMisa\Model\Certificate\HsmCertificate;

class HsmCertificateTest extends TestCase
{
    public function testFromApiRowBuildsCertificate(): void
    {
        $cert = HsmCertificate::fromApiRow([
            'CertificateSN' => 'ABC123',
            'UserName' => 'Test User',
            'AuthOrganizeName' => 'EASYCA',
            'EffectiveTime' => '2026-01-01',
            'ExpirationTime' => '2029-01-01',
        ]);

        self::assertNotNull($cert);
        self::assertSame('ABC123', $cert->getCertificateSn());
        self::assertStringContainsString('EASYCA', $cert->getLabel());
        self::assertStringContainsString('ABC123', $cert->getLabel());
    }

    public function testFromApiRowReturnsNullWhenSnMissing(): void
    {
        self::assertNull(HsmCertificate::fromApiRow(['UserName' => 'x']));
    }
}

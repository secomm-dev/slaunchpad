<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghtk\Test\Unit\Model\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use PHPUnit\Framework\TestCase;
use Secomm\Ghtk\Model\Config\GhtkConfig;

class GhtkConfigTest extends TestCase
{
    private function config(array $values = [], bool $active = false, ?\Closure $decrypt = null): GhtkConfig
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturnCallback(
            function ($path) use ($values) {
                return array_key_exists($path, $values) ? $values[$path] : null;
            }
        );
        $scopeConfig->method('isSetFlag')->willReturn($active);

        $encryptor = $this->createMock(EncryptorInterface::class);
        if ($decrypt !== null) {
            $encryptor->method('decrypt')->willReturnCallback($decrypt);
        }

        return new GhtkConfig($scopeConfig, $encryptor);
    }

    private function v(string $field): string
    {
        return GhtkConfig::XML_PATH_PREFIX . $field;
    }

    public function testApiBaseUrlDefaultsWhenEmpty(): void
    {
        $this->assertSame(GhtkConfig::DEFAULT_BASE_URL, $this->config()->getApiBaseUrl());
    }

    public function testApiBaseUrlOverrideTrimmed(): void
    {
        $cfg = $this->config([$this->v('api_base_url') => 'https://proxy.example.com/']);
        $this->assertSame('https://proxy.example.com', $cfg->getApiBaseUrl());
    }

    public function testApiTokenEmpty(): void
    {
        $this->assertSame('', $this->config()->getApiToken());
    }

    public function testApiTokenDecrypts(): void
    {
        $cfg = $this->config(
            [$this->v('api_token') => 'cipher'],
            false,
            fn ($v) => $v === 'cipher' ? 'plaintext-secret' : 'other'
        );
        $this->assertSame('plaintext-secret', $cfg->getApiToken());
    }

    public function testApiTokenFallsBackToRawOnDecryptFailure(): void
    {
        $cfg = $this->config(
            [$this->v('api_token') => 'not-really-cipher'],
            false,
            function () {
                throw new \RuntimeException('decrypt failed');
            }
        );
        $this->assertSame('not-really-cipher', $cfg->getApiToken());
    }

    public function testRateIncludeParsing(): void
    {
        $this->assertSame([], $this->config()->getRateInclude());
        $this->assertSame(['insurance_fee'], $this->config([$this->v('rate_include') => 'insurance_fee'])->getRateInclude());
        $this->assertSame(
            ['insurance_fee', 'extFees'],
            $this->config([$this->v('rate_include') => 'insurance_fee,extFees'])->getRateInclude()
        );
        $this->assertSame(['insurance_fee'], $this->config([$this->v('rate_include') => ' insurance_fee , '])->getRateInclude());
    }

    public function testWeightUnitDefaultsToKilogram(): void
    {
        $this->assertSame('kg', $this->config()->getWeightUnit());
        $this->assertSame('g', $this->config([$this->v('weight_unit') => 'g'])->getWeightUnit());
        $this->assertSame('kg', $this->config([$this->v('weight_unit') => 'pounds'])->getWeightUnit());
    }

    public function testTimeoutDefaultsWhenInvalid(): void
    {
        $this->assertSame(2, $this->config()->getTimeoutConnect());
        $this->assertSame(5, $this->config([$this->v('timeout_total') => '5'])->getTimeoutTotal());
        $this->assertSame(5, $this->config([$this->v('timeout_total') => 'not-a-number'])->getTimeoutTotal());
    }

    public function testIsActive(): void
    {
        $this->assertFalse($this->config([], false)->isActive());
        $this->assertTrue($this->config([], true)->isActive());
    }
}

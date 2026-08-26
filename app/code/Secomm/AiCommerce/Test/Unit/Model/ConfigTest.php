<?php
declare(strict_types=1);

namespace Secomm\AiCommerce\Test\Unit\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use PHPUnit\Framework\TestCase;
use Secomm\AiCommerce\Model\Config;

/**
 * Endpoint base-path normalization matrix (SPEC-TASK-0X552E §4.5): one
 * normalization authority, default "ai", invalid values never break routing.
 */
class ConfigTest extends TestCase
{
    /**
     * @var ScopeConfigInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    private $scopeConfig;

    /**
     * @var Config
     */
    private $config;

    protected function setUp(): void
    {
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->config = new Config($this->scopeConfig);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function normalizationProvider(): array
    {
        return [
            'default keyword' => ['ai', 'ai'],
            'leading slash' => ['/ai', 'ai'],
            'trailing slash' => ['ai/', 'ai'],
            'surrounding slashes' => ['/ai/', 'ai'],
            'surrounding whitespace' => [' ai ', 'ai'],
            'alternate single segment' => ['agent', 'agent'],
            'multi segment' => ['/api/v1/', 'api/v1'],
        ];
    }

    /**
     * @dataProvider normalizationProvider
     */
    public function testNormalizeEndpointPath(string $raw, string $expected): void
    {
        $this->assertSame($expected, Config::normalizeEndpointPath($raw));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidProvider(): array
    {
        return [
            'empty' => [''],
            'bare slash' => ['/'],
            'query string' => ['ai?debug=1'],
            'fragment' => ['ai#section'],
            'protocol URL' => ['http://example.com/ai'],
            'scheme-relative URL' => ['//example.com/ai'],
            'traversal' => ['../ai'],
            'dot segment' => ['ai/./store'],
            'spaces inside' => ['my ai'],
            'unexpected chars' => ['ai;rm'],
            'overlong' => [str_repeat('a', 65)],
        ];
    }

    /**
     * @dataProvider invalidProvider
     */
    public function testInvalidValuesFallBackToDefault(string $raw): void
    {
        $this->assertSame('ai', Config::normalizeEndpointPath($raw));
    }

    public function testGetEndpointPathReadsScopedConfig(): void
    {
        $this->scopeConfig
            ->expects($this->once())
            ->method('getValue')
            ->with(
                'seocomm_ai_commerce/general/endpoint_path',
                ScopeInterface::SCOPE_STORE,
                3
            )
            ->willReturn('/agent/');

        $this->assertSame('agent', $this->config->getEndpointPath(3));
    }

    public function testGetEndpointPathDefaultsWhenUnconfigured(): void
    {
        $this->scopeConfig->method('getValue')->willReturn(null);

        $this->assertSame('ai', $this->config->getEndpointPath());
    }
}

<?php
declare(strict_types=1);

namespace Secomm\AiDiscoverability\Test\Unit\Service;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Module\Manager;
use PHPUnit\Framework\TestCase;
use Secomm\AiDiscoverability\Service\SeoPolicy;

class SeoPolicyTest extends TestCase
{
    /**
     * @var ScopeConfigInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    private $scopeConfig;

    /**
     * @var Manager|\PHPUnit\Framework\MockObject\MockObject
     */
    private $moduleManager;

    /**
     * @var SeoPolicy
     */
    private $policy;

    protected function setUp(): void
    {
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->moduleManager = $this->createMock(Manager::class);
        $jsonSerializer = new \Magento\Framework\Serialize\Serializer\Json();
        $phpSerializer = new \Magento\Framework\Serialize\Serializer\Serialize();
        $this->policy = new SeoPolicy(
            $this->scopeConfig,
            $this->moduleManager,
            $jsonSerializer,
            $phpSerializer
        );
    }

    public function testNoindexWildcardRuleExcludesMatchingPath(): void
    {
        $this->moduleManager->method('isEnabled')->willReturn(true);
        $this->scopeConfig->method('getValue')
            ->with(SeoPolicy::CONFIG_PATH_NOINDEX_RULES, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, 1)
            ->willReturn(json_encode([['pattern' => '/private-*', 'option' => 1]]));

        $this->assertTrue($this->policy->isNoindexed('/private-pages', 1));
        $this->assertFalse($this->policy->isNoindexed('/about-us', 1));
    }

    public function testIndexFollowRuleDoesNotExclude(): void
    {
        $this->moduleManager->method('isEnabled')->willReturn(true);
        $this->scopeConfig->method('getValue')->willReturn(
            json_encode([['pattern' => '/x*', 'option' => 4]])
        );

        $this->assertFalse($this->policy->isNoindexed('/xyz', 1));
    }

    public function testMirasvitAbsentFailsOpenToIndexable(): void
    {
        $this->moduleManager->method('isEnabled')->willReturn(false);

        $this->assertFalse($this->policy->isNoindexed('/anything', 1));
    }

    public function testSerializedLegacyFormatIsParsed(): void
    {
        $this->moduleManager->method('isEnabled')->willReturn(true);
        $legacy = (new \Magento\Framework\Serialize\Serializer\Serialize())
            ->serialize([['pattern' => '/old*', 'option' => 2]]);
        $this->scopeConfig->method('getValue')->willReturn($legacy);

        $this->assertTrue($this->policy->isNoindexed('/old-page', 1));
    }

    public function testTrailingSlashAppendAndStrip(): void
    {
        $this->moduleManager->method('isEnabled')->willReturn(true);
        $this->scopeConfig->method('getValue')
            ->with(SeoPolicy::CONFIG_PATH_TRAILING_SLASH, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, 1)
            ->willReturn(2); // append

        $this->assertSame('/dresses/', $this->policy->applyTrailingSlash('/dresses', 1));
        $this->assertSame('/about.html', $this->policy->applyTrailingSlash('/about.html', 1));
    }

    public function testTrailingSlashUntouchedWhenMirasvitAbsent(): void
    {
        $this->moduleManager->method('isEnabled')->willReturn(false);

        $this->assertSame('/dresses', $this->policy->applyTrailingSlash('/dresses', 1));
    }
}

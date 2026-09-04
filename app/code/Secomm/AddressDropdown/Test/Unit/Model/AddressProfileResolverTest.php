<?php
declare(strict_types=1);

namespace Secomm\AddressDropdown\Test\Unit\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Serialize\Serializer\Serialize;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Secomm\AddressDropdown\Api\Data\AddressProfileInterface;
use Secomm\AddressDropdown\Api\NoSuchProfileException;
use Secomm\AddressDropdown\Model\AddressProfileResolver;
use Secomm\AddressDropdown\Model\Profile\ProfilePool;

class AddressProfileResolverTest extends TestCase
{
    private ProfilePool&MockObject $pool;
    private ScopeConfigInterface&MockObject $scopeConfig;
    private LoggerInterface&MockObject $logger;

    protected function setUp(): void
    {
        $this->pool = $this->createMock(ProfilePool::class);
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $store = $this->createConfiguredMock(StoreInterface::class, ['getId' => 1]);
        $storeManager = $this->createConfiguredMock(StoreManagerInterface::class, ['getStore' => $store]);
        $this->resolver = new AddressProfileResolver(
            $this->pool,
            $this->scopeConfig,
            $storeManager,
            new Serialize(),
            $this->logger
        );
    }

    private AddressProfileResolver $resolver;

    private function configReturns(mixed $value): void
    {
        $this->scopeConfig->method('getValue')->willReturn($value);
    }

    public function testUnmappedCountryReturnsNull(): void
    {
        $this->configReturns(null);
        $this->assertNull($this->resolver->resolve('AU'));
    }

    public function testEmptyMappingReturnsNull(): void
    {
        $this->configReturns('');
        $this->assertNull($this->resolver->resolve('VN'));
    }

    public function testSerializedMappingResolvesProfile(): void
    {
        $serialized = (new Serialize())->serialize(['VN' => 'vn_current']);
        $this->configReturns($serialized);

        $profile = $this->createMock(AddressProfileInterface::class);
        $this->pool->method('getProfile')->with('vn_current')->willReturn($profile);

        $this->assertSame($profile, $this->resolver->resolve('VN'));
    }

    public function testArrayMappingResolvesProfile(): void
    {
        $this->configReturns(['VN' => 'vn_current']);
        $profile = $this->createMock(AddressProfileInterface::class);
        $this->pool->method('getProfile')->with('vn_current')->willReturn($profile);

        $this->assertSame($profile, $this->resolver->resolve('VN'));
    }

    public function testOtherCountryInMappingIsIgnored(): void
    {
        $this->configReturns(['AU' => 'au_default']);
        $this->assertNull($this->resolver->resolve('VN'));
    }

    public function testMappedButUndeclaredProfileFallsBackToNativeWithWarning(): void
    {
        $this->configReturns(['VN' => 'ghost_profile']);
        $this->pool->method('getProfile')->willThrowException(new NoSuchProfileException(__('missing')));
        $this->logger->expects($this->once())->method('warning');

        $this->assertNull($this->resolver->resolve('VN'));
    }

    public function testCorruptedSerializedValueReturnsNull(): void
    {
        $this->configReturns('definitely-not-serialized');
        $this->assertNull($this->resolver->resolve('VN'));
    }

    public function testContextArgumentDoesNotChangeResolution(): void
    {
        $this->configReturns(['VN' => 'vn_current']);
        $profile = $this->createMock(AddressProfileInterface::class);
        $this->pool->method('getProfile')->willReturn($profile);

        $this->assertSame(
            $this->resolver->resolve('VN'),
            $this->resolver->resolve('VN', AddressProfileResolver::CONTEXT_CHECKOUT)
        );
    }
}

<?php
declare(strict_types=1);

namespace Secomm\VietNamAddress\Test\Unit\Model\Config\Backend;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\VietNamAddress\Model\Config\Backend\ActiveScheme;
use Secomm\VietNamAddress\Model\Scheme\VnSchemeRegistry;
use Secomm\VietNamAddress\Model\Scheme\VnSchemes;

/**
 * DEC-FEATYA2C0W-003 — manual admin flips of active_scheme are rejected unless the
 * target scheme is the installed (registry CURRENT) one.
 */
class ActiveSchemeTest extends TestCase
{
    private VnSchemeRegistry&MockObject $registry;

    private ActiveScheme $backend;

    protected function setUp(): void
    {
        $eventManager = $this->createMock(\Magento\Framework\Event\Manager::class);
        $context = $this->createMock(Context::class);
        $context->method('getEventDispatcher')->willReturn($eventManager);
        $registry = $this->createMock(Registry::class);
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $cacheTypeList = $this->createMock(TypeListInterface::class);
        $this->registry = $this->createMock(VnSchemeRegistry::class);

        $this->backend = new ActiveScheme(
            $context,
            $registry,
            $scopeConfig,
            $cacheTypeList,
            $this->registry,
            $this->createMock(AbstractResource::class),
            $this->createMock(AbstractDb::class)
        );
    }

    public function testAllowsValueEqualToInstalledScheme(): void
    {
        $this->registry->method('getCurrent')->willReturn(VnSchemes::VN_ADMIN_2025);
        $this->backend->setValue(VnSchemes::VN_ADMIN_2025);

        $this->backend->beforeSave();

        $this->assertSame(VnSchemes::VN_ADMIN_2025, $this->backend->getValue());
    }

    public function testRejectsFlipToNotInstalledScheme(): void
    {
        $this->registry->method('getCurrent')->willReturn(VnSchemes::VN_ADMIN_2025);
        $this->backend->setValue(VnSchemes::VN_ADMIN_PRE_2025);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('is not installed');
        $this->backend->beforeSave();
    }

    public function testRejectsUnknownSchemeValue(): void
    {
        $this->registry->method('getCurrent')->willReturn(null);
        $this->backend->setValue('vn_current'); // relative names are never identities

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Unknown Vietnam administrative scheme');
        $this->backend->beforeSave();
    }
}

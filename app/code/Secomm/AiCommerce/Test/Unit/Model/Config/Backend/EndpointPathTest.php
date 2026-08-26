<?php
declare(strict_types=1);

namespace Secomm\AiCommerce\Test\Unit\Model\Config\Backend;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use Magento\Framework\App\Config\Value;
use Magento\Framework\App\Cache\TypeListInterface;
use PHPUnit\Framework\TestCase;
use Secomm\AiCommerce\Model\Config\Backend\EndpointPath;

/**
 * Save-time canonicalization delegates to the single normalization authority
 * (Model\Config::normalizeEndpointPath) — raw admin input never persists.
 */
class EndpointPathTest extends TestCase
{
    public function testBeforeSaveCanonicalizesRawValue(): void
    {
        $backend = new EndpointPath(
            $this->createStub(Context::class),
            $this->createStub(Registry::class),
            $this->createStub(ScopeConfigInterface::class),
            $this->createStub(TypeListInterface::class)
        );
        $backend->setValue(' /api/v1/ ');

        $backend->beforeSave();

        $this->assertSame('api/v1', $backend->getValue());
    }

    public function testBeforeSaveInvalidValueFallsBackToDefault(): void
    {
        $backend = new EndpointPath(
            $this->createStub(Context::class),
            $this->createStub(Registry::class),
            $this->createStub(ScopeConfigInterface::class),
            $this->createStub(TypeListInterface::class)
        );
        $backend->setValue('../ai?x=1');

        $backend->beforeSave();

        $this->assertSame('ai', $backend->getValue());
    }

    public function testBackendExtendsCoreConfigValue(): void
    {
        $this->assertInstanceOf(Value::class, new EndpointPath(
            $this->createStub(Context::class),
            $this->createStub(Registry::class),
            $this->createStub(ScopeConfigInterface::class),
            $this->createStub(TypeListInterface::class)
        ));
    }
}

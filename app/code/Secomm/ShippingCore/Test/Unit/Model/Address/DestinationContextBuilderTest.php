<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Test\Unit\Model\Address;

use Magento\Quote\Model\Quote\Address;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\ShippingCore\Api\Address\CarrierAddressCapabilityInterface;
use Secomm\ShippingCore\Api\Address\RuntimeAddressContextBuilderInterface;
use Secomm\ShippingCore\Api\Address\ShippingAddressResolutionContextInterface;
use Secomm\ShippingCore\Model\Address\DestinationContextBuilder;

/**
 * TASK-T78YH6 / TASK-7AJ3K8 — the quote builder is a THIN adapter over the shared scalar
 * runtime context builder: it contributes country, city_id, native-city locality, street
 * (PII hygiene) and the capability target scheme — identity resolution itself is covered
 * by RuntimeAddressContextBuilderTest.
 */
class DestinationContextBuilderTest extends TestCase
{
    private RuntimeAddressContextBuilderInterface&MockObject $runtimeBuilder;
    private DestinationContextBuilder $builder;

    protected function setUp(): void
    {
        $this->runtimeBuilder = $this->createMock(RuntimeAddressContextBuilderInterface::class);
        $this->builder = new DestinationContextBuilder($this->runtimeBuilder);
    }

    public function testDelegatesScalarFieldsToTheRuntimeBuilder(): void
    {
        $context = $this->createMock(ShippingAddressResolutionContextInterface::class);
        $capability = $this->capability();

        $this->runtimeBuilder->expects($this->once())->method('build')->with(
            'VN',
            521,
            12345,
            'Phường Hàng Trống',
            $capability,
            '12 Nguyễn Huệ, Quận 1'
        )->willReturn($context);

        $this->assertSame($context, $this->builder->build($this->destination(), $capability));
    }

    public function testMissingCityIdPassesNullNotZero(): void
    {
        $this->runtimeBuilder->expects($this->once())->method('build')->with(
            'VN',
            521,
            null,
            'Phường Bến Nghé',
            $this->anything(),
            null
        )->willReturn($this->createMock(ShippingAddressResolutionContextInterface::class));

        $destination = $this->createMock(Address::class);
        $destination->method('getCountryId')->willReturn('VN');
        $destination->method('getRegionId')->willReturn('521');
        $destination->method('getData')->with('city_id')->willReturn(null);
        $destination->method('getCity')->willReturn('Phường Bến Nghé');
        $destination->method('getStreet')->willReturn([' ']);

        $this->builder->build($destination, $this->capability());
    }

    public function testStreetWithoutLinesYieldsNullStreetText(): void
    {
        $this->runtimeBuilder->expects($this->once())->method('build')->with(
            'VN',
            521,
            12345,
            'Phường Hàng Trống',
            $this->anything(),
            null
        )->willReturn($this->createMock(ShippingAddressResolutionContextInterface::class));

        $destination = $this->createMock(Address::class);
        $destination->method('getCountryId')->willReturn('VN');
        $destination->method('getRegionId')->willReturn('521');
        $destination->method('getData')->with('city_id')->willReturn(12345);
        $destination->method('getCity')->willReturn('Phường Hàng Trống');
        $destination->method('getStreet')->willReturn(['', '   ']);

        $this->builder->build($destination, $this->capability());
    }

    public function testUnlocalizedDestinationPassesZeroRegion(): void
    {
        // Magento Quote\Address cannot be constructed bare in unit tests (29 constructor args) —
        // a mock with missing ids stands in for an unlocalized destination.
        $this->runtimeBuilder->expects($this->once())->method('build')->with(
            'VN',
            0,
            null,
            null,
            $this->anything(),
            null
        )->willReturn($this->createMock(ShippingAddressResolutionContextInterface::class));

        $destination = $this->createMock(Address::class);
        $destination->method('getCountryId')->willReturn('VN');
        $destination->method('getRegionId')->willReturn(null);
        $destination->method('getData')->with('city_id')->willReturn(null);
        $destination->method('getCity')->willReturn(null);
        $destination->method('getStreet')->willReturn([]);

        $this->builder->build($destination, $this->capability());
    }

    /**
     * @return Address&MockObject
     */
    private function destination(): Address|MockObject
    {
        $destination = $this->createMock(Address::class);
        $destination->method('getCountryId')->willReturn('VN');
        $destination->method('getRegionId')->willReturn('521');
        $destination->method('getData')->with('city_id')->willReturn(12345);
        $destination->method('getCity')->willReturn('Phường Hàng Trống');
        $destination->method('getStreet')->willReturn(['12 Nguyễn Huệ', ' ', 'Quận 1']);

        return $destination;
    }

    /**
     * Capability stub whose textual-fallback declaration explodes if consulted outside the
     * unresolved branch — the builder must never need it beyond getRequiredScheme().
     */
    private function capability(): CarrierAddressCapabilityInterface
    {
        return new class implements CarrierAddressCapabilityInterface {
            public function getRequiredScheme(): string
            {
                return 'VN_ADMIN_PRE_2025';
            }

            public function supportsTextualFallback(): bool
            {
                throw new \LogicException('Builder must not consult supportsTextualFallback().');
            }
        };
    }
}

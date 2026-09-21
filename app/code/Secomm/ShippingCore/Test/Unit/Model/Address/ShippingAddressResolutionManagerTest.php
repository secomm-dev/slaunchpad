<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Test\Unit\Model\Address;

use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\ShippingCore\Api\Address\CarrierAddressCapabilityInterface;
use Secomm\ShippingCore\Api\Address\ShippingAddressResolutionContextInterface;
use Secomm\ShippingCore\Model\Address\Exception\UnsupportedDestinationException;
use Secomm\ShippingCore\Model\Address\ShippingAddressResolutionContext;
use Secomm\ShippingCore\Model\Address\ShippingAddressResolutionManager;
use Secomm\VietNamAddress\Api\Data\VnAddressResolutionInterface;
use Secomm\VietNamAddress\Api\VnAdminAddressResolverInterface;
use Secomm\VietNamAddress\Model\Data\VnAddressResolutionData;
use Secomm\VietNamAddress\Model\Scheme\VnSchemes;

/**
 * TASK-5XDG1P — local canonical orchestration over a MOCKED VietNamAddress resolver:
 * 4-state passthrough, AMBIGUOUS never auto-selected, canonical-keyed request cache,
 * non-VN bypass, invalid-context handling.
 */
class ShippingAddressResolutionManagerTest extends TestCase
{
    private const SOURCE_SCHEME = VnSchemes::VN_ADMIN_2025;
    private const SOURCE_UNIT = 'VNA25-0A1B2C3D4E';
    private const TARGET_SCHEME = VnSchemes::VN_ADMIN_PRE_2025;
    private const TARGET_UNIT = 'VNAP25-9F8E7D6C5B';

    private VnAdminAddressResolverInterface&MockObject $adminResolver;
    private ShippingAddressResolutionManager $manager;

    protected function setUp(): void
    {
        $this->adminResolver = $this->createMock(VnAdminAddressResolverInterface::class);
        $this->manager = new ShippingAddressResolutionManager($this->adminResolver);
    }

    public function testExactResolvesToTargetUnitAndIsResolved(): void
    {
        $this->adminResolver->expects($this->once())->method('resolve')
            ->with(self::SOURCE_SCHEME, self::SOURCE_UNIT, self::TARGET_SCHEME)
            ->willReturn($this->canonical(VnAddressResolutionInterface::STATUS_EXACT, self::TARGET_UNIT));

        $result = $this->manager->resolve($this->context(), $this->capability());

        self::assertSame(VnAddressResolutionInterface::STATUS_EXACT, $result->getStatus());
        self::assertSame(self::TARGET_SCHEME, $result->getSchemeCode());
        self::assertSame(self::TARGET_UNIT, $result->getUnitCode());
        self::assertSame([], $result->getCandidateCodes());
        self::assertTrue($result->isResolved());
    }

    public function testMappedSingleCandidateResolvesToTargetUnit(): void
    {
        $this->adminResolver->expects($this->once())->method('resolve')
            ->with(self::SOURCE_SCHEME, self::SOURCE_UNIT, self::TARGET_SCHEME)
            ->willReturn($this->canonical(VnAddressResolutionInterface::STATUS_MAPPED, self::TARGET_UNIT));

        $result = $this->manager->resolve($this->context(), $this->capability());

        self::assertSame(VnAddressResolutionInterface::STATUS_MAPPED, $result->getStatus());
        self::assertSame(self::TARGET_UNIT, $result->getUnitCode());
        self::assertSame([], $result->getCandidateCodes());
        self::assertTrue($result->isResolved());
    }

    public function testAmbiguousNeverSelectsACandidate(): void
    {
        $candidates = ['VNAP25-B2B2B2B2B2', 'VNAP25-A1A1A1A1A1'];
        $this->adminResolver->expects($this->once())->method('resolve')
            ->willReturn($this->canonical(VnAddressResolutionInterface::STATUS_AMBIGUOUS, null, $candidates));

        $result = $this->manager->resolve($this->context(), $this->capability());

        self::assertSame(VnAddressResolutionInterface::STATUS_AMBIGUOUS, $result->getStatus());
        self::assertNull($result->getUnitCode());
        // Directive §20 — no first-candidate fallback: the unit code is neither candidate.
        self::assertNotSame($candidates[0], $result->getUnitCode());
        self::assertNotSame($candidates[1], $result->getUnitCode());
        self::assertSame($candidates, $result->getCandidateCodes());
        self::assertFalse($result->isResolved());
    }

    public function testUnmappedHasNoUnitCodeAndNoCandidates(): void
    {
        $this->adminResolver->expects($this->once())->method('resolve')
            ->willReturn($this->canonical(VnAddressResolutionInterface::STATUS_UNMAPPED));

        $result = $this->manager->resolve($this->context(), $this->capability());

        self::assertSame(VnAddressResolutionInterface::STATUS_UNMAPPED, $result->getStatus());
        self::assertNull($result->getUnitCode());
        self::assertSame([], $result->getCandidateCodes());
        self::assertFalse($result->isResolved());
    }

    public function testSameCanonicalLookupResolvesOnceAndReturnsCachedInstance(): void
    {
        $this->adminResolver->expects($this->once())->method('resolve')
            ->willReturn($this->canonical(VnAddressResolutionInterface::STATUS_MAPPED, self::TARGET_UNIT));

        $first = $this->manager->resolve($this->context(), $this->capability());
        $second = $this->manager->resolve($this->context(), $this->capability());

        self::assertSame($first, $second);
    }

    public function testDifferentSourceUnitGetsSeparateResolution(): void
    {
        $this->adminResolver->expects($this->exactly(2))->method('resolve')
            ->willReturnCallback(
                fn (string $sourceScheme, string $sourceCode, string $targetScheme): VnAddressResolutionData =>
                    $this->canonical(
                        VnAddressResolutionInterface::STATUS_MAPPED,
                        str_replace('VNA25-', 'VNAP25-', $sourceCode),
                        sourceCodeOverride: $sourceCode,
                        targetSchemeOverride: $targetScheme,
                        sourceSchemeOverride: $sourceScheme
                    )
            );

        $first = $this->manager->resolve($this->context(sourceUnitCode: 'VNA25-FIRST1'), $this->capability());
        $second = $this->manager->resolve($this->context(sourceUnitCode: 'VNA25-SECOND'), $this->capability());

        self::assertSame('VNAP25-FIRST1', $first->getUnitCode());
        self::assertSame('VNAP25-SECOND', $second->getUnitCode());
        self::assertNotSame($first, $second);
    }

    public function testDifferentTargetSchemeGetsSeparateResolution(): void
    {
        $this->adminResolver->expects($this->exactly(2))->method('resolve')
            ->willReturnCallback(
                fn (string $sourceScheme, string $sourceCode, string $targetScheme): VnAddressResolutionData =>
                    $this->canonical(
                        VnAddressResolutionInterface::STATUS_MAPPED,
                        'VNAP25-TARGETED',
                        targetSchemeOverride: $targetScheme
                    )
            );

        $pre = $this->manager->resolve($this->context(), $this->capability(VnSchemes::VN_ADMIN_PRE_2025));
        $current = $this->manager->resolve(
            $this->context(targetScheme: VnSchemes::VN_ADMIN_2025),
            $this->capability(VnSchemes::VN_ADMIN_2025)
        );

        self::assertSame(VnSchemes::VN_ADMIN_PRE_2025, $pre->getSchemeCode());
        self::assertSame(VnSchemes::VN_ADMIN_2025, $current->getSchemeCode());
    }

    public function testAmbiguousOutcomeIsCachedWithinTheRequest(): void
    {
        $candidates = ['VNAP25-B2B2B2B2B2', 'VNAP25-A1A1A1A1A1'];
        $this->adminResolver->expects($this->once())->method('resolve')
            ->willReturn($this->canonical(VnAddressResolutionInterface::STATUS_AMBIGUOUS, null, $candidates));

        $first = $this->manager->resolve($this->context(), $this->capability());
        $second = $this->manager->resolve($this->context(), $this->capability());

        self::assertSame($first, $second);
        self::assertSame($candidates, $second->getCandidateCodes());
    }

    public function testUnmappedOutcomeIsCachedWithinTheRequest(): void
    {
        $this->adminResolver->expects($this->once())->method('resolve')
            ->willReturn($this->canonical(VnAddressResolutionInterface::STATUS_UNMAPPED));

        $first = $this->manager->resolve($this->context(), $this->capability());
        $second = $this->manager->resolve($this->context(), $this->capability());

        self::assertSame($first, $second);
        self::assertFalse($second->isResolved());
    }

    public function testMissingSourceSchemeResolvesUnmappedWithoutTouchingTheResolver(): void
    {
        $this->adminResolver->expects($this->never())->method('resolve');

        $result = $this->manager->resolve($this->context(sourceScheme: null), $this->capability());
        $again = $this->manager->resolve($this->context(sourceScheme: null), $this->capability());

        self::assertSame(VnAddressResolutionInterface::STATUS_UNMAPPED, $result->getStatus());
        self::assertNull($result->getUnitCode());
        self::assertSame([], $result->getCandidateCodes());
        self::assertFalse($result->isResolved());
        self::assertSame($result, $again);
    }

    public function testMissingSourceUnitCodeResolvesUnmappedWithoutTouchingTheResolver(): void
    {
        $this->adminResolver->expects($this->never())->method('resolve');

        $result = $this->manager->resolve($this->context(sourceUnitCode: null), $this->capability());

        self::assertSame(VnAddressResolutionInterface::STATUS_UNMAPPED, $result->getStatus());
        self::assertNull($result->getUnitCode());
        self::assertFalse($result->isResolved());
    }

    public function testNonVnDestinationBypassesResolution(): void
    {
        $this->adminResolver->expects($this->never())->method('resolve');

        try {
            $this->manager->resolve($this->context(countryId: 'US'), $this->capability());
            self::fail('Expected UnsupportedDestinationException for a non-Vietnam destination.');
        } catch (UnsupportedDestinationException) {
            // First bypass proven — the resolver mock's ->never() expectation still holds.
        }

        // Repeated bypass with the same destination: still no resolver call, nothing cached.
        $this->expectException(UnsupportedDestinationException::class);
        $this->manager->resolve($this->context(countryId: 'US'), $this->capability());
    }

    public function testNonVnDestinationBypassIsCaseInsensitive(): void
    {
        $this->adminResolver->expects($this->never())->method('resolve');

        $this->expectException(UnsupportedDestinationException::class);
        $this->manager->resolve($this->context(countryId: 'us'), $this->capability());
    }

    public function testUnknownCountryStillAttemptsCanonicalResolution(): void
    {
        // countryId = null does not prove "non-VN" — the canonical identity stays authoritative.
        $this->adminResolver->expects($this->once())->method('resolve')
            ->willReturn($this->canonical(VnAddressResolutionInterface::STATUS_EXACT, self::TARGET_UNIT));

        $result = $this->manager->resolve($this->context(countryId: null), $this->capability());

        self::assertTrue($result->isResolved());
    }

    public function testUnknownSchemeCodePropagatesLocalizedException(): void
    {
        $this->adminResolver->expects($this->once())->method('resolve')
            ->willThrowException(new LocalizedException(__('Unknown Vietnam administrative scheme.')));

        $this->expectException(LocalizedException::class);
        $this->manager->resolve($this->context(), $this->capability());
    }

    /**
     * TASK-7AJ3K8 — an AMBIGUOUS name-bridge match arrives as "no identity + context
     * candidates": the manager surfaces AMBIGUOUS (candidates pass through untouched, the
     * canonical resolver is never consulted — the candidates ARE canonical codes).
     */
    public function testIdentityMissingWithContextCandidatesSurfacesAmbiguous(): void
    {
        $this->adminResolver->expects($this->never())->method('resolve');

        $context = new ShippingAddressResolutionContext(
            countryId: 'VN',
            sourceScheme: null,
            sourceUnitCode: null,
            targetScheme: self::TARGET_SCHEME,
            candidateCodes: ['VNA25-AAAA', 'VNA25-BBBB']
        );

        $result = $this->manager->resolve($context, $this->capability());

        self::assertSame(VnAddressResolutionInterface::STATUS_AMBIGUOUS, $result->getStatus());
        self::assertNull($result->getUnitCode());
        self::assertSame(['VNA25-AAAA', 'VNA25-BBBB'], $result->getCandidateCodes());
        self::assertFalse($result->isResolved());
    }

    /**
     * Capability stub whose textual-fallback declaration explodes if the LOCAL manager ever
     * consults it (directive §12 — textual fallback is a later phase).
     */
    private function capability(string $requiredScheme = self::TARGET_SCHEME): CarrierAddressCapabilityInterface
    {
        return new class ($requiredScheme) implements CarrierAddressCapabilityInterface {
            public function __construct(private readonly string $scheme)
            {
            }

            public function getRequiredScheme(): string
            {
                return $this->scheme;
            }

            public function supportsTextualFallback(): bool
            {
                throw new \LogicException('Local resolution must not consult supportsTextualFallback().');
            }
        };
    }

    private function context(
        ?string $countryId = 'VN',
        ?string $sourceScheme = self::SOURCE_SCHEME,
        ?string $sourceUnitCode = self::SOURCE_UNIT,
        string $targetScheme = self::TARGET_SCHEME
    ): ShippingAddressResolutionContextInterface {
        return new ShippingAddressResolutionContext(
            countryId: $countryId,
            sourceScheme: $sourceScheme,
            sourceUnitCode: $sourceUnitCode,
            targetScheme: $targetScheme
        );
    }

    private function canonical(
        string $status,
        ?string $resolvedCode = null,
        array $candidateCodes = [],
        string $sourceSchemeOverride = self::SOURCE_SCHEME,
        string $sourceCodeOverride = self::SOURCE_UNIT,
        string $targetSchemeOverride = self::TARGET_SCHEME
    ): VnAddressResolutionData {
        return new VnAddressResolutionData(
            $sourceSchemeOverride,
            $sourceCodeOverride,
            $targetSchemeOverride,
            $status,
            $resolvedCode,
            null,
            $candidateCodes,
            null
        );
    }
}

<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Test\Unit\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\Ghn\Model\Config;
use Secomm\Ghn\Model\GhnShipmentConstraints;
use Secomm\ShippingCore\Api\Config\CarrierDestinationScopeConfigInterface;

/**
 * TASK-RJFTPZ / AC-A2 — config reader: defaults, encrypted token handling, environment → base URL,
 * timeout accessors. The token must only ever leave the class decrypted, and only via getApiToken().
 */
class ConfigTest extends TestCase
{
    private ScopeConfigInterface&MockObject $scopeConfig;

    private EncryptorInterface&MockObject $encryptor;

    private Config $config;

    protected function setUp(): void
    {
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->encryptor = $this->createMock(EncryptorInterface::class);
        // FEAT-QA23PZ: the shared destination-scope reader is a new ctor dependency —
        // a mock keeps these config tests focused on the GHN-owned accessors.
        $this->config = new Config(
            $this->scopeConfig,
            $this->encryptor,
            $this->createMock(CarrierDestinationScopeConfigInterface::class)
        );
    }

    public function testEmptyTokenReturnsEmptyStringWithoutDecrypting(): void
    {
        $this->scopeConfig->method('getValue')->with(Config::XML_PATH_API_TOKEN)->willReturn('');
        $this->encryptor->expects($this->never())->method('decrypt');

        $this->assertSame('', $this->config->getApiToken());
    }

    public function testConfigLivesUnderTheStandardDeliveryMethodsSection(): void
    {
        // TL directive 2026-09-10 — carrier config must sit in Sales → Delivery Methods
        // (carriers/secomm_ghn/*), not a custom Secomm tab. The `enabled` flag was replaced
        // by the Magento-standard carrier `active` gate in TASK-FMBBSD slice 2 (read via
        // AbstractCarrier::getConfigFlag, not this Config reader).
        $this->assertStringStartsWith('carriers/secomm_ghn/', Config::XML_PATH_API_TOKEN);
        $this->assertStringStartsWith('carriers/secomm_ghn/', Config::XML_PATH_REQUEST_TIMEOUT);
    }

    public function testEncryptedTokenIsDecrypted(): void
    {
        $this->scopeConfig->method('getValue')->willReturn('encrypted-value');
        $this->encryptor->method('decrypt')->with('encrypted-value')->willReturn('plain-token');

        $this->assertSame('plain-token', $this->config->getApiToken());
    }

    public function testUnknownEnvironmentFallsBackToSandbox(): void
    {
        $this->scopeConfig->method('getValue')->with(Config::XML_PATH_ENVIRONMENT)->willReturn('junk');

        $this->assertSame(Config::ENV_SANDBOX, $this->config->getEnvironment());
        $this->assertSame(
            'https://dev-online-gateway.ghn.vn/shiip/public-api',
            $this->config->getBaseUrl()
        );
    }

    public function testEmptyEnvironmentFallsBackToSandbox(): void
    {
        $this->scopeConfig->method('getValue')->willReturn('');

        $this->assertSame(Config::ENV_SANDBOX, $this->config->getEnvironment());
    }

    public function testProductionEnvironmentResolvesProductionBaseUrl(): void
    {
        $this->scopeConfig->method('getValue')->willReturn(Config::ENV_PRODUCTION);

        $this->assertSame(Config::ENV_PRODUCTION, $this->config->getEnvironment());
        $this->assertSame(
            'https://online-gateway.ghn.vn/shiip/public-api',
            $this->config->getBaseUrl()
        );
    }

    public function testTimeoutsAreCastsToInteger(): void
    {
        $matcher = $this->exactly(2);
        $this->scopeConfig->expects($matcher)
            ->method('getValue')
            ->willReturnCallback(function (string $path) use ($matcher): string {
                match ($matcher->numberOfInvocations()) {
                    1 => $this->assertSame(Config::XML_PATH_CONNECTION_TIMEOUT, $path),
                    2 => $this->assertSame(Config::XML_PATH_REQUEST_TIMEOUT, $path),
                };

                return $path === Config::XML_PATH_CONNECTION_TIMEOUT ? '10' : '30';
            });

        $this->assertSame(10, $this->config->getConnectionTimeout());
        $this->assertSame(30, $this->config->getRequestTimeout());
    }

    public function testPaymentTypeIsCastToInteger(): void
    {
        $this->scopeConfig->method('getValue')->with(Config::XML_PATH_PAYMENT_TYPE)->willReturn('2');

        $this->assertSame(2, $this->config->getPaymentType());
    }

    public function testOriginDistrictIdIsCastToInteger(): void
    {
        $this->scopeConfig->method('getValue')->willReturnCallback(
            fn (string $path): string => $path === Config::XML_PATH_ORIGIN_DISTRICT_ID ? '235' : ''
        );

        $this->assertSame(235, $this->config->getOriginDistrictId());
    }

    public function testUnsetOriginDistrictIdReadsAsZero(): void
    {
        $this->scopeConfig->method('getValue')->willReturn(null);

        $this->assertSame(0, $this->config->getOriginDistrictId());
    }

    public function testDebugEnabledReadsFlag(): void
    {
        $this->scopeConfig->method('isSetFlag')->with(Config::XML_PATH_DEBUG)->willReturn(true);

        $this->assertTrue($this->config->isDebugEnabled());
    }

    /**
     * TASK-ZS2B41 (rev. 3-path) — dimension limits fall back to GhnShipmentConstraints when
     * the config is empty (fresh merchant config resolves deterministically without a save).
     */
    public function testDimensionLimitsFallBackToConstraintsWhenConfigEmpty(): void
    {
        $this->scopeConfig->method('getValue')->willReturn(null);

        $this->assertSame(GhnShipmentConstraints::MAX_SIDE_CM, $this->config->getMaxLengthCm());
        $this->assertSame(GhnShipmentConstraints::MAX_SIDE_CM, $this->config->getMaxWidthCm());
        $this->assertSame(GhnShipmentConstraints::MAX_SIDE_CM, $this->config->getMaxHeightCm());
    }

    /** TASK-ZS2B41 (rev. 3-path) — positive configured values override the constraint defaults (and cast). */
    public function testDimensionLimitsUseConfiguredPositiveValuesPerDimension(): void
    {
        $this->scopeConfig->method('getValue')->willReturnCallback(
            fn (string $path): ?string => match ($path) {
                Config::XML_PATH_MAX_LENGTH_CM => '120',
                Config::XML_PATH_MAX_WIDTH_CM => '130',
                Config::XML_PATH_MAX_HEIGHT_CM => '140',
                default => null,
            }
        );

        $this->assertSame(120, $this->config->getMaxLengthCm());
        $this->assertSame(130, $this->config->getMaxWidthCm());
        $this->assertSame(140, $this->config->getMaxHeightCm());
    }

    /** TASK-ZS2B41 (rev. 3-path) — empty/non-positive config never disables a hard limit. */
    public function testNonPositiveDimensionConfigFallsBackToConstraints(): void
    {
        $this->scopeConfig->method('getValue')->willReturnCallback(
            fn (string $path): ?string => match ($path) {
                Config::XML_PATH_MAX_LENGTH_CM => '0',
                Config::XML_PATH_MAX_HEIGHT_CM => '-5',
                default => null,
            }
        );

        $this->assertSame(GhnShipmentConstraints::MAX_SIDE_CM, $this->config->getMaxLengthCm());
        $this->assertSame(GhnShipmentConstraints::MAX_SIDE_CM, $this->config->getMaxHeightCm());
    }

    /** TASK-WNQCRW — the weight gate falls back to the frozen CREATE cap when config is empty. */
    public function testPackageWeightLimitFallsBackToConstraintsWhenConfigEmpty(): void
    {
        $this->scopeConfig->method('getValue')->willReturn(null);

        $this->assertSame(GhnShipmentConstraints::TYPE_5_MAX_WEIGHT_G, $this->config->getMaxPackageWeightG());
    }

    /** TASK-WNQCRW — positive configured weight overrides the contract default (and casts). */
    public function testPackageWeightLimitUsesConfiguredPositiveValue(): void
    {
        $this->scopeConfig->method('getValue')->willReturnCallback(
            fn (string $path): ?string => match ($path) {
                Config::XML_PATH_MAX_PACKAGE_WEIGHT_G => '60000',
                default => null,
            }
        );

        $this->assertSame(60000, $this->config->getMaxPackageWeightG());
    }

    /** TASK-WNQCRW — empty/non-positive weight config never disables the gate. */
    public function testNonPositivePackageWeightConfigFallsBackToConstraints(): void
    {
        $this->scopeConfig->method('getValue')->willReturnCallback(
            fn (string $path): ?string => match ($path) {
                Config::XML_PATH_MAX_PACKAGE_WEIGHT_G => '0',
                default => null,
            }
        );

        $this->assertSame(GhnShipmentConstraints::TYPE_5_MAX_WEIGHT_G, $this->config->getMaxPackageWeightG());
    }
}

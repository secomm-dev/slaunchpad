<?php
declare(strict_types=1);

namespace Secomm\AiCommerce\Test\Unit\Service\Response;

use Magento\Store\Model\Store;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\AiCommerce\Service\Response\StoreDto;

/**
 * Pins the public StoreDto V1 field allowlist: store_code, locale, currency
 * and base_url — the exact contract llms.txt documents for `/ai/store`.
 *
 * Any field added here is a public API expansion and must be a deliberate,
 * documented contract change (with this test updated alongside it) — never a
 * silent internal-field leak or a doc drift.
 */
class StoreDtoTest extends TestCase
{
    /**
     * The DTO emits exactly the V1 allowlist — keys and values.
     */
    public function testToArrayEmitsExactlyTheV1Allowlist(): void
    {
        $store = $this->createStoreMock();

        $this->assertSame(
            [
                'store_code' => 'default',
                'locale' => 'vi_VN',
                'currency' => 'VND',
                'base_url' => 'https://example.com',
            ],
            (new StoreDto())->toArray($store)
        );
    }

    /**
     * Internal store identifiers never leak into the public payload.
     */
    public function testInternalStoreFieldsAreNeverExposed(): void
    {
        $store = $this->createStoreMock();
        $store->method('getWebsiteId')->willReturn(1);
        $store->method('getStoreGroupId')->willReturn(2);
        $store->method('getName')->willReturn('Internal Store View Name');

        $dto = (new StoreDto())->toArray($store);

        $this->assertSame(['store_code', 'locale', 'currency', 'base_url'], array_keys($dto));
        foreach (['website_id', 'store_group_id', 'name', 'extension_attributes'] as $forbidden) {
            $this->assertArrayNotHasKey($forbidden, $dto);
        }
        $this->assertStringNotContainsString('Internal Store View Name', (string) json_encode($dto));
    }

    /**
     * base_url normalization (trailing slashes stripped) is part of the
     * shipped contract.
     */
    public function testBaseUrlTrailingSlashesAreNormalized(): void
    {
        $store = $this->createStoreMock();
        $store->method('getBaseUrl')->willReturn('https://example.com///');

        $this->assertSame(
            'https://example.com',
            (new StoreDto())->toArray($store)['base_url']
        );

        $store = $this->createStoreMock();
        $store->method('getBaseUrl')->willReturn('https://example.com');

        $this->assertSame(
            'https://example.com',
            (new StoreDto())->toArray($store)['base_url']
        );
    }

    /**
     * The concrete Store model is mocked (not StoreInterface): getConfig,
     * getBaseUrl and getDefaultCurrencyCode live on the model, which is what
     * the runtime actually passes in.
     *
     * @return Store&MockObject
     */
    private function createStoreMock(): MockObject
    {
        $store = $this->createMock(Store::class);
        $store->method('getCode')->willReturn('default');
        $store->method('getConfig')->willReturn('vi_VN');
        $store->method('getDefaultCurrencyCode')->willReturn('VND');
        $store->method('getBaseUrl')->willReturn('https://example.com/');

        return $store;
    }
}

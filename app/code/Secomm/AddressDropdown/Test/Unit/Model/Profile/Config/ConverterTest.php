<?php
declare(strict_types=1);

namespace Secomm\AddressDropdown\Test\Unit\Model\Profile\Config;

use DOMDocument;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;
use Secomm\AddressDropdown\Model\Profile\Config\Converter;

class ConverterTest extends TestCase
{
    private function convertXml(string $xml): array
    {
        $dom = new DOMDocument();
        $dom->loadXML($xml);

        return (new Converter())->convert($dom);
    }

    public function testConvertsProfileWithDefaults(): void
    {
        $result = $this->convertXml(
            '<config><profile code="p1" label="LBL_KEY" country="VN"><levels>'
            . '<level entity_type="region" label="State/Province" sort_order="20" required="false"/>'
            . '<level entity_type="city" depth="1" label="City" placeholder="Pick one" sort_order="10"'
            . ' required="true" translate="label"/>'
            . '</levels></profile></config>'
        );

        $this->assertSame(['p1'], array_keys($result['profiles']));
        $profile = $result['profiles']['p1'];
        $this->assertSame('p1', $profile['code']);
        $this->assertSame('LBL_KEY', $profile['label']);
        $this->assertSame('VN', $profile['country_id']);

        [$region, $city] = $profile['levels'];
        // Region: unspecified depth defaults to 0; label key passes through untranslated.
        $this->assertSame('region', $region['entity_type']);
        $this->assertSame(0, $region['depth']);
        $this->assertSame('State/Province', $region['label']);
        $this->assertFalse($region['required']);
        $this->assertSame('', $region['placeholder']);

        $this->assertSame('city', $city['entity_type']);
        $this->assertSame(1, $city['depth']);
        $this->assertSame('Pick one', $city['placeholder']);
        $this->assertTrue($city['required']);
        $this->assertSame('label', $city['translate']);
    }

    public function testDefaultSortOrderAndRequiredWhenOmitted(): void
    {
        $result = $this->convertXml(
            '<config><profile code="p"><levels>'
            . '<level entity_type="region" label="R"/>'
            . '</levels></profile></config>'
        );
        $level = $result['profiles']['p']['levels'][0];
        $this->assertSame(100, $level['sort_order']);
        $this->assertTrue($level['required']);
    }

    public function testDuplicateEntityTypeDepthSlotThrows(): void
    {
        $this->expectException(LocalizedException::class);
        $this->convertXml(
            '<config><profile code="p"><levels>'
            . '<level entity_type="city" depth="1" label="A"/>'
            . '<level entity_type="city" depth="1" label="B"/>'
            . '</levels></profile></config>'
        );
    }

    public function testCityDepthBelowOneThrows(): void
    {
        $this->expectException(LocalizedException::class);
        $this->convertXml(
            '<config><profile code="p"><levels>'
            . '<level entity_type="city" depth="0" label="Bad"/>'
            . '</levels></profile></config>'
        );
    }

    public function testMissingProfileCodeThrows(): void
    {
        $this->expectException(LocalizedException::class);
        $this->convertXml('<config><profile><levels><level entity_type="region" label="R"/></levels></profile></config>');
    }
}

<?php
declare(strict_types=1);

namespace Secomm\AddressDropdown\Test\Unit\Model\Resolver;

use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\AddressDropdown\Api\AddressProfileResolverInterface;
use Secomm\AddressDropdown\Api\AddressSchemaProviderInterface;
use Secomm\AddressDropdown\Api\Data\AddressProfileInterface;
use Secomm\AddressDropdown\Api\Data\SchemaLevelInterface;
use Secomm\AddressDropdown\Api\NoSuchProfileException;
use Secomm\AddressDropdown\Model\Profile\ProfilePool;
use Secomm\AddressDropdown\Model\Resolver\AddressSchemaGraphql;

class AddressSchemaGraphqlTest extends TestCase
{
    private AddressProfileResolverInterface&MockObject $profileResolver;
    private AddressSchemaProviderInterface&MockObject $schemaProvider;
    private ProfilePool&MockObject $pool;

    private AddressSchemaGraphql $resolver;

    protected function setUp(): void
    {
        $this->profileResolver = $this->createMock(AddressProfileResolverInterface::class);
        $this->schemaProvider = $this->createMock(AddressSchemaProviderInterface::class);
        $this->pool = $this->createMock(ProfilePool::class);
        $this->resolver = new AddressSchemaGraphql(
            $this->profileResolver,
            $this->schemaProvider,
            $this->pool
        );
    }

    private function resolve(array $input): array
    {
        $field = $this->createMock(\Magento\Framework\GraphQl\Config\Element\Field::class);
        $info = $this->createMock(\Magento\Framework\GraphQl\Schema\Type\ResolveInfo::class);

        return $this->resolver->resolve($field, null, $info, null, ['input' => $input]);
    }

    private function level(array $data): SchemaLevelInterface
    {
        return new \Secomm\AddressDropdown\Model\Data\SchemaLevelData([
            SchemaLevelInterface::ENTITY_TYPE => $data['entity_type'],
            SchemaLevelInterface::DEPTH => $data['depth'],
            SchemaLevelInterface::LABEL => $data['label'],
            SchemaLevelInterface::PLACEHOLDER => $data['placeholder'] ?? null,
            SchemaLevelInterface::SORT_ORDER => $data['sort_order'],
            SchemaLevelInterface::REQUIRED => $data['required'] ?? true,
        ]);
    }

    public function testRequiresCountryId(): void
    {
        $this->expectException(GraphQlInputException::class);
        $this->resolve([]);
    }

    public function testUnmappedCountryReturnsEmptySchema(): void
    {
        $this->profileResolver->method('resolve')->with('AU')->willReturn(null);
        $this->assertSame(['profile_code' => null, 'levels' => []], $this->resolve(['country_id' => 'AU']));
    }

    public function testCountryDefaultProfileMapsLevels(): void
    {
        $profile = $this->createConfiguredMock(AddressProfileInterface::class, ['getCode' => 'vn_current']);
        $this->profileResolver->method('resolve')->with('VN')->willReturn($profile);
        $this->schemaProvider->method('getSchema')->with('vn_current')->willReturn([
            $this->level(['entity_type' => 'region', 'depth' => 0, 'label' => 'Province/City', 'sort_order' => 10]),
            $this->level(['entity_type' => 'city', 'depth' => 1, 'label' => 'Ward/Commune',
                          'placeholder' => 'Please select a Ward/Commune.', 'sort_order' => 20]),
        ]);

        $result = $this->resolve(['country_id' => 'VN']);

        $this->assertSame('vn_current', $result['profile_code']);
        $this->assertCount(2, $result['levels']);
        $this->assertSame('region', $result['levels'][0]['entity_type']);
        $this->assertSame('Ward/Commune', $result['levels'][1]['label']); // __() passthrough in unit context
        $this->assertSame('Please select a Ward/Commune.', $result['levels'][1]['placeholder']);
        $this->assertTrue($result['levels'][1]['required']);
    }

    public function testExplicitProfileCodeBypassesCountryResolution(): void
    {
        $profile = $this->createConfiguredMock(AddressProfileInterface::class, ['getCode' => 'vn_legacy']);
        $this->pool->method('getProfile')->with('vn_legacy')->willReturn($profile);
        $this->schemaProvider->method('getSchema')->with('vn_legacy')->willReturn([
            $this->level(['entity_type' => 'region', 'depth' => 0, 'label' => 'Province/City', 'sort_order' => 10]),
        ]);
        $this->profileResolver->expects($this->never())->method('resolve');

        $result = $this->resolve(['country_id' => 'VN', 'profile_code' => 'vn_legacy']);
        $this->assertSame('vn_legacy', $result['profile_code']);
        $this->assertCount(1, $result['levels']);
    }

    public function testUnknownExplicitProfileBecomesNoSuchEntity(): void
    {
        $this->pool->method('getProfile')->willThrowException(new NoSuchProfileException(__('gone')));
        $this->expectException(GraphQlNoSuchEntityException::class);
        $this->resolve(['country_id' => 'VN', 'profile_code' => 'ghost']);
    }
}

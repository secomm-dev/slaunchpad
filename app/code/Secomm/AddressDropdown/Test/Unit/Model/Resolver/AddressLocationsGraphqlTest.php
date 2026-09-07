<?php
declare(strict_types=1);

namespace Secomm\AddressDropdown\Test\Unit\Model\Resolver;

use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\AddressDropdown\Api\Data\LocationNodeInterface;
use Secomm\AddressDropdown\Api\LocationHierarchyProviderInterface;
use Secomm\AddressDropdown\Api\NoSuchProfileException;
use Secomm\AddressDropdown\Model\Profile\ProfilePool;
use Secomm\AddressDropdown\Model\Resolver\AddressLocationsGraphql;

class AddressLocationsGraphqlTest extends TestCase
{
    private LocationHierarchyProviderInterface&MockObject $provider;

    private ProfilePool&MockObject $profilePool;

    private AddressLocationsGraphql $resolver;

    protected function setUp(): void
    {
        $this->provider = $this->createMock(LocationHierarchyProviderInterface::class);
        $this->profilePool = $this->createMock(ProfilePool::class);
        $this->resolver = new AddressLocationsGraphql($this->provider, $this->profilePool);
    }

    private function resolve(array $input): array
    {
        $field = $this->createMock(\Magento\Framework\GraphQl\Config\Element\Field::class);
        $info = $this->createMock(\Magento\Framework\GraphQl\Schema\Type\ResolveInfo::class);

        return $this->resolver->resolve($field, null, $info, null, ['input' => $input]);
    }

    public function testRequiresProfileCode(): void
    {
        $this->expectException(GraphQlInputException::class);
        $this->resolve(['region_id' => 5]);
    }

    public function testRejectsBothParentSelectors(): void
    {
        $this->expectException(GraphQlInputException::class);
        $this->resolve(['region_id' => 5, 'parent_city_id' => 9, 'profile_code' => 'vn_current']);
    }

    public function testRejectsNeitherParentSelector(): void
    {
        $this->expectException(GraphQlInputException::class);
        $this->resolve(['profile_code' => 'vn_current']);
    }

    public function testRejectsNonPositiveIds(): void
    {
        $this->expectException(GraphQlInputException::class);
        $this->resolve(['region_id' => 0, 'profile_code' => 'vn_current']);
    }

    public function testRootLocationsMapToOutputShape(): void
    {
        $node = $this->createConfiguredMock(LocationNodeInterface::class, [
            'getCityId' => 42,
            'getDefaultName' => 'Ward A',
            'getName' => 'Phường A',
            'getDepth' => 1,
            'getParentCityId' => null,
            'getRegionId' => 1185,
            'hasChildren' => true,
        ]);
        $this->provider->method('getRootLocations')->with(1185, 'vn_current')->willReturn([$node]);

        $result = $this->resolve(['region_id' => 1185, 'profile_code' => 'vn_current']);

        $this->assertSame([
            'city_id' => 42,
            'default_name' => 'Ward A',
            'name' => 'Phường A',
            'label' => 'Phường A',
            'depth' => 1,
            'parent_city_id' => null,
            'region_id' => 1185,
            'has_children' => true,
        ], $result[0]);
    }

    public function testChildLocationsUseChildMethod(): void
    {
        $this->provider->expects($this->once())->method('getChildLocations')->with(42, 'vn_legacy')->willReturn([]);
        $this->assertSame([], $this->resolve(['parent_city_id' => 42, 'profile_code' => 'vn_legacy']));
    }

    public function testUnknownProfileBecomesNoSuchEntity(): void
    {
        // ProfilePool genuinely throws for undeclared codes — no provider fallback may kick in.
        $this->profilePool->method('getProfile')->willThrowException(new NoSuchProfileException(__('gone')));
        $this->provider->expects($this->never())->method('getRootLocations');
        $this->expectException(GraphQlNoSuchEntityException::class);
        $this->resolve(['region_id' => 1, 'profile_code' => 'ghost']);
    }
}

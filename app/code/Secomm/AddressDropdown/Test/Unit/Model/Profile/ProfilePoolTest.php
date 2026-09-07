<?php
declare(strict_types=1);

namespace Secomm\AddressDropdown\Test\Unit\Model\Profile;

use Magento\Framework\Config\DataInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\AddressDropdown\Api\NoSuchProfileException;
use Secomm\AddressDropdown\Model\Profile\ProfilePool;

class ProfilePoolTest extends TestCase
{
    private function pool(array $profiles): ProfilePool
    {
        /** @var DataInterface&MockObject $configData */
        $configData = $this->createMock(DataInterface::class);
        $configData->method('get')->with('profiles', [])->willReturn($profiles);

        return new ProfilePool($configData);
    }

    public static function profileFixture(): array
    {
        return [
            'profiles' => [
                'default' => [
                    'code' => 'default',
                    'label' => 'Default Address Profile',
                    'country_id' => null,
                    'levels' => [
                        ['entity_type' => 'region', 'depth' => 0, 'label' => 'State/Province',
                         'placeholder' => 'Pick', 'sort_order' => 10, 'required' => true, 'translate' => 'label'],
                        ['entity_type' => 'city', 'depth' => 1, 'label' => 'City',
                         'placeholder' => '', 'sort_order' => 20, 'required' => true, 'translate' => null],
                    ],
                ],
            ],
        ];
    }

    public function testBuildsProfileDtosFromConfig(): void
    {
        $pool = $this->pool(self::profileFixture()['profiles']);

        $this->assertSame(['default'], array_keys($pool->getAll()));
        $profile = $pool->getProfile('default');
        $this->assertSame('default', $profile->getCode());
        $this->assertSame('Default Address Profile', $profile->getLabel());
        $this->assertNull($profile->getCountryId());
        $this->assertCount(2, $profile->getLevels());
        $this->assertSame('region', $profile->getLevels()[0]->getEntityType());
        $this->assertSame(1, $profile->getLevels()[1]->getDepth());
        $this->assertSame('City', $profile->getLevels()[1]->getLabel());
        $this->assertTrue($profile->getLevels()[0]->isRequired());
    }

    public function testHasProfile(): void
    {
        $pool = $this->pool(self::profileFixture()['profiles']);
        $this->assertTrue($pool->hasProfile('default'));
        $this->assertFalse($pool->hasProfile('missing'));
    }

    public function testUnknownProfileThrows(): void
    {
        $this->expectException(NoSuchProfileException::class);
        $this->pool([])->getProfile('nope');
    }

    public function testEmptyConfigYieldsEmptyPool(): void
    {
        $this->assertSame([], $this->pool([])->getAll());
    }
}

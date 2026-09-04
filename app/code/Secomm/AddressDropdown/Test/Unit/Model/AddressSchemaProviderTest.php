<?php
declare(strict_types=1);

namespace Secomm\AddressDropdown\Test\Unit\Model;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\AddressDropdown\Api\Data\AddressProfileInterface;
use Secomm\AddressDropdown\Api\Data\SchemaLevelInterface;
use Secomm\AddressDropdown\Api\NoSuchProfileException;
use Secomm\AddressDropdown\Model\AddressSchemaProvider;
use Secomm\AddressDropdown\Model\Profile\ProfilePool;

class AddressSchemaProviderTest extends TestCase
{
    private function level(int $sortOrder, string $label): SchemaLevelInterface
    {
        return new \Secomm\AddressDropdown\Model\Data\SchemaLevelData([
            SchemaLevelInterface::SORT_ORDER => $sortOrder,
            SchemaLevelInterface::LABEL => $label,
        ]);
    }

    public function testReturnsLevelsSortedBySortOrder(): void
    {
        $profile = $this->createConfiguredMock(AddressProfileInterface::class, [
            'getLevels' => [$this->level(30, 'C'), $this->level(10, 'A'), $this->level(20, 'B')],
        ]);
        $pool = $this->createConfiguredMock(ProfilePool::class, ['getProfile' => $profile]);

        $schema = (new AddressSchemaProvider($pool))->getSchema('default');

        $this->assertSame(['A', 'B', 'C'], array_map(
            static fn (SchemaLevelInterface $level): string => $level->getLabel(),
            $schema
        ));
    }

    public function testStableSortKeepsDeclarationOrderOnTies(): void
    {
        $profile = $this->createConfiguredMock(AddressProfileInterface::class, [
            'getLevels' => [$this->level(10, 'First'), $this->level(10, 'Second')],
        ]);
        /** @var ProfilePool&MockObject $pool */
        $pool = $this->createConfiguredMock(ProfilePool::class, ['getProfile' => $profile]);

        $schema = (new AddressSchemaProvider($pool))->getSchema('x');

        $this->assertSame(['First', 'Second'], array_map(
            static fn (SchemaLevelInterface $level): string => $level->getLabel(),
            $schema
        ));
    }

    public function testUnknownProfileThrows(): void
    {
        $pool = $this->createMock(ProfilePool::class);
        $pool->method('getProfile')->willThrowException(new NoSuchProfileException(__('missing')));

        $this->expectException(NoSuchProfileException::class);
        (new AddressSchemaProvider($pool))->getSchema('ghost');
    }
}

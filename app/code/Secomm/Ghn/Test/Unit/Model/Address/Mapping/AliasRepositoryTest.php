<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Test\Unit\Model\Address\Mapping;

use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;
use Secomm\Ghn\Model\Address\Mapping\AliasRepository;

/**
 * TASK-MZ2TCB / AC-B5 — curated alias CSV parsing: header required, blank lines skipped,
 * malformed row fails LOUD (never silently skipped).
 */
class AliasRepositoryTest extends TestCase
{
    public function testParsesValidFile(): void
    {
        $path = $this->writeFile(<<<'CSV'
secomm_unit_code,ghn_provider_key,note
VNA25-0000000001,21511,manual fix
VNA25-0000000002,1442,

CSV);

        $aliases = (new AliasRepository($this->createMock(\Magento\Framework\Component\ComponentRegistrar::class)))
            ->parseFile($path);

        $this->assertSame(['VNA25-0000000001' => '21511', 'VNA25-0000000002' => '1442'], $aliases);
    }

    public function testBadHeaderFailsLoud(): void
    {
        $path = $this->writeFile("unit_code,key\nVNA25-0000000001,1\n");

        $this->expectException(LocalizedException::class);
        (new AliasRepository($this->createMock(\Magento\Framework\Component\ComponentRegistrar::class)))
            ->parseFile($path);
    }

    public function testEmptyValueFailsLoud(): void
    {
        $path = $this->writeFile("secomm_unit_code,ghn_provider_key,note\nVNA25-0000000001,\n");

        $this->expectException(LocalizedException::class);
        (new AliasRepository($this->createMock(\Magento\Framework\Component\ComponentRegistrar::class)))
            ->parseFile($path);
    }

    private function writeFile(string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'ghn_alias_') . '.csv';
        file_put_contents($path, $content);

        return $path;
    }
}

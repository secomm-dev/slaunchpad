<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Test\Unit\Model\Address\Dataset;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\TestCase;
use Secomm\Ghn\Model\Address\Dataset\Manifest;

/**
 * TASK-TBM30R / AC-L1/L2 — manifest build/read/validate (checksum + record count).
 */
class ManifestTest extends TestCase
{
    private Manifest $manifest;

    private string $dir;

    protected function setUp(): void
    {
        $this->manifest = new Manifest(new Json());
        $this->dir = sys_get_temp_dir() . '/ghn_manifest_' . uniqid();
        mkdir($this->dir . '/master', 0775, true);
    }

    protected function tearDown(): void
    {
        @unlink($this->dir . '/master/X.csv');
        @unlink($this->dir . '/manifest.json');
        @rmdir($this->dir . '/master');
        @rmdir($this->dir);
    }

    public function testReadWriteRoundTrip(): void
    {
        $file = $this->dir . '/master/X.csv';
        file_put_contents($file, "a,b\n1,2\n");

        $data = $this->manifest->build('2026.09.10', '2026-09-10T00:00:00+00:00', 'sandbox', [
            'master/X.csv' => ['scheme_code' => 'GHN_ADMIN_2025', 'record_count' => 1, 'sha256' => hash_file('sha256', $file)],
        ]);
        $this->manifest->write($this->dir . '/manifest.json', $data);

        $read = $this->manifest->read($this->dir . '/manifest.json');
        $this->assertSame('2026.09.10', $read['dataset_version']);
        $this->assertSame(1, $read['files']['master/X.csv']['record_count']);

        $this->manifest->validateFile($read, $file, 'master/X.csv', 1);
        $this->addToAssertionCount(1); // no exception = valid
    }

    public function testChecksumMismatchFailsLoud(): void
    {
        $file = $this->dir . '/master/X.csv';
        file_put_contents($file, "tampered\n");

        $manifest = ['files' => ['master/X.csv' => ['sha256' => str_repeat('0', 64), 'record_count' => 1]]];

        $this->expectException(LocalizedException::class);
        $this->manifest->validateFile($manifest, $file, 'master/X.csv', 1);
    }

    public function testCountMismatchFailsLoud(): void
    {
        $file = $this->dir . '/master/X.csv';
        file_put_contents($file, "a,b\n1,2\n");

        $sha = hash_file('sha256', $file);
        $manifest = ['files' => ['master/X.csv' => ['sha256' => $sha, 'record_count' => 5]]];

        $this->expectException(LocalizedException::class);
        $this->manifest->validateFile($manifest, $file, 'master/X.csv', 1);
    }

    public function testMissingManifestReadsAsNull(): void
    {
        $this->assertNull($this->manifest->read($this->dir . '/manifest.json'));
    }

    public function testMalformedManifestFailsLoud(): void
    {
        file_put_contents($this->dir . '/manifest.json', '{not-json');

        $this->expectException(LocalizedException::class);
        $this->manifest->read($this->dir . '/manifest.json');
    }
}

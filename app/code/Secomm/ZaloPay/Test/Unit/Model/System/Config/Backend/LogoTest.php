<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\ZaloPay\Test\Unit\Model\System\Config\Backend;

use PHPUnit\Framework\TestCase;
use Secomm\ZaloPay\Model\System\Config\Backend\Logo;

/**
 * UNIT - checkout logo upload allow-list (TASK-MCHN2T feature C, AC10):
 * PNG/JPG/JPEG/WEBP only. SVG (out of scope per issue #8) and every other
 * vector/raster format outside the list is rejected at upload time.
 */
class LogoTest extends TestCase
{
    /**
     * @return void
     */
    public function testAllowedExtensionsArePngJpegWebpOnly(): void
    {
        $reflection = new \ReflectionMethod(Logo::class, '_getAllowedExtensions');
        $reflection->setAccessible(true);

        $extensions = $reflection->invoke(
            (new \ReflectionClass(Logo::class))->newInstanceWithoutConstructor()
        );

        $this->assertSame(['png', 'jpg', 'jpeg', 'webp'], $extensions);
        $this->assertNotContains('svg', $extensions);
        $this->assertNotContains('gif', $extensions);
    }
}

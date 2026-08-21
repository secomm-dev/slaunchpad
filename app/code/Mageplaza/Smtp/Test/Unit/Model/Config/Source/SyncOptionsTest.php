<?php
/**
 * Mageplaza
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Mageplaza.com license that is
 * available through the world-wide-web at this URL:
 * https://www.mageplaza.com/LICENSE.txt
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this extension to newer
 * version in the future.
 *
 * @category    Mageplaza
 * @package     Mageplaza_Smtp
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */
declare(strict_types=1);

namespace Mageplaza\Smtp\Test\Unit\Model\Config\Source;

use Mageplaza\Smtp\Model\Config\Source\SyncOptions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SyncOptions::class)]
class SyncOptionsTest extends TestCase
{
    private SyncOptions $subject;

    protected function setUp(): void
    {
        $this->subject = new SyncOptions();
    }

    public function testToOptionArrayReturnsAllAndNotSyncOptions(): void
    {
        // assertEquals: nested __() Phrase objects aren't === identical across instances.
        $this->assertEquals(
            [
                ['value' => SyncOptions::ALL, 'label' => __('All')],
                ['value' => SyncOptions::NOT_SYNC, 'label' => __('New Object Only')],
            ],
            $this->subject->toOptionArray()
        );
    }
}

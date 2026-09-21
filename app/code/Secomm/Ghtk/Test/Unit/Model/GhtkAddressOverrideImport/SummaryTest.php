<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghtk\Test\Unit\Model\GhtkAddressOverrideImport;

use PHPUnit\Framework\TestCase;
use Secomm\Ghtk\Model\GhtkAddressOverrideImport\Summary;

class SummaryTest extends TestCase
{
    public function testDefaults(): void
    {
        $s = new Summary();
        $this->assertFalse($s->isCommitted());
        $this->assertFalse($s->hasErrors());
        $this->assertSame(0, $s->getInserted());
        $this->assertSame(0, $s->getFailed());
    }

    public function testIncrementAndCommit(): void
    {
        $s = (new Summary())
            ->incrementInserted(2)
            ->incrementUpdated(1)
            ->incrementRemoved(3)
            ->incrementSkipped(1)
            ->markCommitted();

        $this->assertTrue($s->isCommitted());
        $this->assertSame(2, $s->getInserted());
        $this->assertSame(1, $s->getUpdated());
        $this->assertSame(3, $s->getRemoved());
        $this->assertSame(1, $s->getSkipped());
    }

    public function testErrors(): void
    {
        $s = (new Summary())->addError('bad row')->addError('worse row')->setFailed(2);
        $this->assertTrue($s->hasErrors());
        $this->assertSame(['bad row', 'worse row'], $s->getErrors());
        $this->assertSame(2, $s->getFailed());
    }
}

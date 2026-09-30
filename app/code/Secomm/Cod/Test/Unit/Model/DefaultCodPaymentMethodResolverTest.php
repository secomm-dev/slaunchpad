<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Cod\Test\Unit\Model;

use PHPUnit\Framework\TestCase;
use Secomm\Cod\Model\DefaultCodPaymentMethodResolver;

/**
 * TASK-DFGFZ9 phase 3 (DEC-TASKDFGFZ9-003) — P1 identification: exactly the Magento core
 * Cash-On-Delivery method code, hardcoded (no config, no admin field). Exact, case-sensitive
 * match on the trimmed code. Fresh-install safe: nothing to configure, no config row read.
 */
class DefaultCodPaymentMethodResolverTest extends TestCase
{
    private DefaultCodPaymentMethodResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new DefaultCodPaymentMethodResolver();
    }

    public function testCoreCashOnDeliveryMethodIsCod(): void
    {
        $this->assertTrue($this->resolver->isCod('cashondelivery'));
    }

    public function testQueriedCodeIsTrimmedBeforeComparison(): void
    {
        $this->assertTrue($this->resolver->isCod(' cashondelivery '));
    }

    public function testOtherPaymentMethodsAreNotCod(): void
    {
        $resolver = $this->resolver;
        $this->assertFalse($resolver->isCod('mollie'));
        $this->assertFalse($resolver->isCod('checkmo'));
        $this->assertFalse($resolver->isCod('banktransfer'));
        $this->assertFalse($resolver->isCod('free'));
    }

    public function testEmptyMethodIsNotCod(): void
    {
        $this->assertFalse($this->resolver->isCod(''));
    }

    public function testSimilarAndPrefixCodesAreNotMatches(): void
    {
        $resolver = $this->resolver;
        $this->assertFalse($resolver->isCod('cashondel'));
        $this->assertFalse($resolver->isCod('cashondelivery_extra'));
        $this->assertFalse($resolver->isCod('CASHONDELIVERY'));
    }

    public function testDefaultListContainsExactlyTheCoreMethod(): void
    {
        $this->assertSame(['cashondelivery'], DefaultCodPaymentMethodResolver::DEFAULT_COD_METHODS);
    }
}

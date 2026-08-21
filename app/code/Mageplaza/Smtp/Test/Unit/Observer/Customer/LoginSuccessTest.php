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

namespace Mageplaza\Smtp\Test\Unit\Observer\Customer;

use Exception;
use Magento\Framework\Event\Observer;
use Magento\PageCache\Model\Cache\Type;
use Mageplaza\Smtp\Helper\Data;
use Mageplaza\Smtp\Helper\EmailMarketing;
use Mageplaza\Smtp\Observer\Customer\LoginSuccess;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Zend_Cache;

#[CoversClass(LoginSuccess::class)]
class LoginSuccessTest extends TestCase
{
    private Type&MockObject $fullPageCache;
    private Data&MockObject $helperData;
    private EmailMarketing&MockObject $helperEmailMarketing;

    private LoginSuccess $observer;

    protected function setUp(): void
    {
        $this->fullPageCache        = $this->createMock(Type::class);
        $this->helperData           = $this->createMock(Data::class);
        $this->helperEmailMarketing = $this->createMock(EmailMarketing::class);

        $this->observer = new LoginSuccess(
            $this->fullPageCache,
            $this->helperData,
            $this->helperEmailMarketing
        );
    }

    public function testExecuteCleansCacheWhenMarketingEnabled(): void
    {
        $this->helperData->method('getScopeId')->willReturn(1);
        $this->helperEmailMarketing->method('isEnableEmailMarketing')->with(1)->willReturn(true);

        $this->fullPageCache->expects($this->once())
            ->method('clean')
            ->with(Zend_Cache::CLEANING_MODE_MATCHING_ANY_TAG, [EmailMarketing::CACHE_TAG]);

        $this->observer->execute($this->createMock(Observer::class));
    }

    public function testExecuteSkipsCleanWhenMarketingDisabled(): void
    {
        $this->helperData->method('getScopeId')->willReturn(1);
        $this->helperEmailMarketing->method('isEnableEmailMarketing')->willReturn(false);

        $this->fullPageCache->expects($this->never())->method('clean');

        $this->observer->execute($this->createMock(Observer::class));
    }

    public function testExecuteFallsBackToNullScopeWhenGetScopeIdThrows(): void
    {
        $this->helperData->method('getScopeId')->willThrowException(new Exception('no scope'));
        $this->helperEmailMarketing->expects($this->once())
            ->method('isEnableEmailMarketing')
            ->with(null)
            ->willReturn(false);

        $this->fullPageCache->expects($this->never())->method('clean');

        $this->observer->execute($this->createMock(Observer::class));
    }
}

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

namespace Mageplaza\Smtp\Test\Unit\Plugin;

use Exception;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\AccountManagement as CustomerAccountManagement;
use Magento\Framework\DataObject;
use Magento\Framework\TestFramework\Unit\Helper\MockCreationTrait;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Mageplaza\Smtp\Helper\EmailMarketing;
use Mageplaza\Smtp\Plugin\AccountManagement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(AccountManagement::class)]
class AccountManagementTest extends TestCase
{
    use MockCreationTrait;

    private CheckoutSession&MockObject $checkoutSession;
    private CartRepositoryInterface&MockObject $cartRepository;
    private EmailMarketing&MockObject $helper;
    private CustomerAccountManagement&MockObject $subject;

    private AccountManagement $plugin;

    protected function setUp(): void
    {
        $this->checkoutSession = $this->createMock(CheckoutSession::class);
        $this->cartRepository  = $this->createMock(CartRepositoryInterface::class);
        $this->helper          = $this->createMock(EmailMarketing::class);
        $this->subject         = $this->createMock(CustomerAccountManagement::class);

        $this->plugin = new AccountManagement($this->checkoutSession, $this->cartRepository, $this->helper);
    }

    private function enableGate(): void
    {
        $this->helper->method('isEnableEmailMarketing')->willReturn(true);
        $this->helper->method('getSecretKey')->willReturn('secret');
        $this->helper->method('getAppID')->willReturn('app');
    }

    public function testAfterIsEmailAvailableReturnsResultWhenGuardFalse(): void
    {
        $this->helper->method('isEnableEmailMarketing')->willReturn(false);
        $this->cartRepository->expects($this->never())->method('get');

        $result = $this->plugin->afterIsEmailAvailable($this->subject, true, 'a@b.com');

        $this->assertTrue($result);
    }

    public function testAfterIsEmailAvailableReturnsResultWhenCartIdMissing(): void
    {
        $this->enableGate();
        $this->checkoutSession->method('getQuote')->willReturn(new DataObject());
        $this->cartRepository->expects($this->never())->method('get');

        $result = $this->plugin->afterIsEmailAvailable($this->subject, true, 'a@b.com');

        $this->assertTrue($result);
    }

    public function testAfterIsEmailAvailableSavesQuoteEmailWhenCartPresent(): void
    {
        $this->enableGate();
        $this->checkoutSession->method('getQuote')->willReturn(new DataObject(['id' => 42]));

        // setCustomerEmail() is magic on Quote (@method docblock only, no declared implementation
        // — vendor/magento/module-quote/Model/Quote.php:55) — needs createPartialMockWithReflection.
        $quote = $this->createPartialMockWithReflection(Quote::class, ['setCustomerEmail']);
        $this->cartRepository->expects($this->once())->method('get')->with(42)->willReturn($quote);
        $quote->expects($this->once())->method('setCustomerEmail')->with('buyer@example.com');
        $this->cartRepository->expects($this->once())->method('save')->with($quote);

        $result = $this->plugin->afterIsEmailAvailable($this->subject, false, 'buyer@example.com');

        $this->assertFalse($result);
    }

    public function testAfterIsEmailAvailableReturnsResultWhenSaveThrows(): void
    {
        $this->enableGate();
        $this->checkoutSession->method('getQuote')->willReturn(new DataObject(['id' => 42]));

        $quote = $this->createPartialMockWithReflection(Quote::class, ['setCustomerEmail']);
        $this->cartRepository->method('get')->with(42)->willReturn($quote);
        $this->cartRepository->method('save')->with($quote)->willThrowException(new Exception('db down'));

        $result = $this->plugin->afterIsEmailAvailable($this->subject, true, 'buyer@example.com');

        $this->assertTrue($result);
    }
}

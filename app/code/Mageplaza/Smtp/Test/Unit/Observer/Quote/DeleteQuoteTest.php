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

namespace Mageplaza\Smtp\Test\Unit\Observer\Quote;

use Exception;
use Magento\Framework\DataObject;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Mageplaza\Smtp\Helper\EmailMarketing;
use Mageplaza\Smtp\Observer\Quote\DeleteQuote;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(DeleteQuote::class)]
class DeleteQuoteTest extends TestCase
{
    private EmailMarketing&MockObject $helperMock;
    private LoggerInterface&MockObject $loggerMock;
    private DeleteQuote $observer;

    protected function setUp(): void
    {
        $this->helperMock = $this->createMock(EmailMarketing::class);
        $this->loggerMock = $this->createMock(LoggerInterface::class);

        $this->observer = new DeleteQuote($this->helperMock, $this->loggerMock);
    }

    // getDataObject() is magic on Event (Magic Method Registry) — a real Event
    // instance exercises the real __call->getData path instead of mocking it.
    private function observerWithQuote(DataObject $quote): Observer&MockObject
    {
        $event = new Event(['data_object' => $quote]);
        $observer = $this->createMock(Observer::class);
        $observer->method('getEvent')->willReturn($event);

        return $observer;
    }

    private function enableGate(): void
    {
        $this->helperMock->method('isEnableEmailMarketing')->willReturn(true);
        $this->helperMock->method('getSecretKey')->willReturn('secret');
        $this->helperMock->method('getAppID')->willReturn('app');
    }


    public function testExecuteSkipsWhenEmailMarketingDisabled(): void
    {
        $this->helperMock->method('isEnableEmailMarketing')->willReturn(false);
        $this->helperMock->expects($this->never())->method('deleteQuote');

        $this->observer->execute($this->observerWithQuote(new DataObject(['id' => 10])));
    }

    public function testExecuteSkipsWhenSecretKeyMissing(): void
    {
        $this->helperMock->method('isEnableEmailMarketing')->willReturn(true);
        $this->helperMock->method('getSecretKey')->willReturn('');
        $this->helperMock->expects($this->never())->method('deleteQuote');

        $this->observer->execute($this->observerWithQuote(new DataObject(['id' => 10])));
    }

    public function testExecuteSkipsWhenAppIdMissing(): void
    {
        $this->helperMock->method('isEnableEmailMarketing')->willReturn(true);
        $this->helperMock->method('getSecretKey')->willReturn('secret');
        $this->helperMock->method('getAppID')->willReturn('');
        $this->helperMock->expects($this->never())->method('deleteQuote');

        $this->observer->execute($this->observerWithQuote(new DataObject(['id' => 10])));
    }

    public function testExecuteDeletesQuoteWhenGateOpenAndQuoteHasId(): void
    {
        $this->enableGate();
        $quote = new DataObject(['id' => 10, 'store_id' => 2]);

        $this->helperMock->expects($this->once())
            ->method('deleteQuote')
            ->with(10, 2);

        $this->observer->execute($this->observerWithQuote($quote));
    }

    public function testExecuteIgnoresQuoteWithoutId(): void
    {
        $this->enableGate();
        $this->helperMock->expects($this->never())->method('deleteQuote');

        $this->observer->execute($this->observerWithQuote(new DataObject()));
    }

    public function testExecuteLogsExceptionInsteadOfBubbling(): void
    {
        $this->enableGate();
        $quote = new DataObject(['id' => 10, 'store_id' => 2]);

        $this->helperMock->method('deleteQuote')
            ->willThrowException(new Exception('boom'));
        $this->loggerMock->expects($this->once())->method('critical')->with('boom');

        $this->observer->execute($this->observerWithQuote($quote));
    }
}

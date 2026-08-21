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

use Magento\Framework\App\ObjectManager;
use Magento\Framework\DataObject;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Framework\Json\Helper\Data as JsonHelper;
use Magento\Framework\ObjectManagerInterface;
use Magento\Quote\Model\ResourceModel\Quote as QuoteResource;
use Mageplaza\Smtp\Helper\EmailMarketing;
use Mageplaza\Smtp\Observer\Quote\SyncQuote;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(SyncQuote::class)]
class SyncQuoteTest extends TestCase
{
    private EmailMarketing&MockObject $helperMock;
    private LoggerInterface&MockObject $loggerMock;

    private SyncQuote $sut;

    protected function setUp(): void
    {
        $this->helperMock = $this->createMock(EmailMarketing::class);
        $this->loggerMock = $this->createMock(LoggerInterface::class);

        $this->sut = new SyncQuote($this->helperMock, $this->loggerMock);

        // EmailMarketing::jsonEncode()/jsonDecode() resolve the Json helper through
        // the global ObjectManager; stub it so the static calls do not fall back.
        $jsonHelper = $this->createMock(JsonHelper::class);
        $jsonHelper->method('jsonEncode')->willReturnCallback(static fn ($v) => json_encode($v));
        $jsonHelper->method('jsonDecode')->willReturnCallback(static fn ($v) => json_decode($v, true));
        $om = $this->createMock(ObjectManagerInterface::class);
        $om->method('get')->willReturn($jsonHelper);
        ObjectManager::setInstance($om);
    }

    private function observerWithQuote(DataObject $quote): Observer&MockObject
    {
        $event = new Event(['quote' => $quote]);
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

    private function createResourceQuoteMock(AdapterInterface&MockObject $connection): QuoteResource&MockObject
    {
        $resourceQuote = $this->getMockBuilder(QuoteResource::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getConnection', 'getMainTable'])
            ->getMock();
        $resourceQuote->method('getConnection')->willReturn($connection);
        $resourceQuote->method('getMainTable')->willReturn('quote');

        return $resourceQuote;
    }

    public function testExecuteSkipsWhenEmailMarketingDisabled(): void
    {
        $this->helperMock->method('isEnableEmailMarketing')->willReturn(false);
        $this->helperMock->expects($this->never())->method('getSecretKey');
        $this->helperMock->expects($this->never())->method('getACEData');

        $this->sut->execute($this->observerWithQuote(new DataObject(['items_count' => 1])));
    }

    public function testExecuteSkipsWhenSecretKeyMissing(): void
    {
        $this->helperMock->method('isEnableEmailMarketing')->willReturn(true);
        $this->helperMock->method('getSecretKey')->willReturn('');
        $this->helperMock->expects($this->never())->method('getAppID');
        $this->helperMock->expects($this->never())->method('getACEData');

        $this->sut->execute($this->observerWithQuote(new DataObject(['items_count' => 1])));
    }

    public function testExecuteSkipsWhenAppIdMissing(): void
    {
        $this->helperMock->method('isEnableEmailMarketing')->willReturn(true);
        $this->helperMock->method('getSecretKey')->willReturn('secret');
        $this->helperMock->method('getAppID')->willReturn('');
        $this->helperMock->expects($this->never())->method('getACEData');

        $this->sut->execute($this->observerWithQuote(new DataObject(['items_count' => 1])));
    }

    public function testExecuteSkipsEmptyCartWithNoPriorAceLogData(): void
    {
        $this->enableGate();
        $this->helperMock->expects($this->never())->method('getACEData');

        $this->sut->execute($this->observerWithQuote(new DataObject(['items_count' => 0])));
    }

    public function testExecuteSyncsAbandonedCartWithPriorAceLogData(): void
    {
        $this->enableGate();
        // Items count dropped to 0, but stale log data still means "was tracked" — must resync.
        $quote = new DataObject([
            'id'                   => 7,
            'items_count'          => 0,
            'mp_smtp_ace_log_data' => json_encode(['cart' => 'old']),
        ]);

        $this->helperMock->method('getACEData')->with($quote)->willReturn(['cart' => 'new']);

        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects($this->once())->method('update');
        $this->helperMock->method('getResourceQuote')->willReturn($this->createResourceQuoteMock($connection));

        $this->helperMock->expects($this->once())
            ->method('sendRequestWithoutWaitResponse')
            ->with(['cart' => 'new'], EmailMarketing::CHECKOUT_URL);

        $this->sut->execute($this->observerWithQuote($quote));
    }

    public function testExecuteSyncsChangedCartData(): void
    {
        $this->enableGate();
        // No prior mp_smtp_ace_log_data key at all — oldACEData must default to [] and still sync.
        $quote = new DataObject(['id' => 7, 'items_count' => 2]);

        $this->helperMock->method('getACEData')->with($quote)->willReturn(['cart' => 'data']);

        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects($this->once())
            ->method('update')
            ->with('quote', ['mp_smtp_ace_log_data' => json_encode(['cart' => 'data'])], ['entity_id = ?' => 7]);
        $this->helperMock->method('getResourceQuote')->willReturn($this->createResourceQuoteMock($connection));

        $this->helperMock->expects($this->once())
            ->method('sendRequestWithoutWaitResponse')
            ->with(['cart' => 'data'], EmailMarketing::CHECKOUT_URL);

        $this->sut->execute($this->observerWithQuote($quote));
    }

    public function testExecuteSkipsUpdateWhenAceDataUnchanged(): void
    {
        $this->enableGate();
        $quote = new DataObject([
            'id'                   => 7,
            'items_count'          => 2,
            'mp_smtp_ace_log_data' => json_encode(['cart' => 'same']),
        ]);

        $this->helperMock->method('getACEData')->with($quote)->willReturn(['cart' => 'same']);
        $this->helperMock->expects($this->never())->method('getResourceQuote');
        $this->helperMock->expects($this->never())->method('sendRequestWithoutWaitResponse');

        $this->sut->execute($this->observerWithQuote($quote));
    }

    public function testExecuteSkipsSyncWhenCheckoutAlreadyCompleted(): void
    {
        $this->enableGate();
        $quote = new DataObject([
            'id'                   => 7,
            'items_count'          => 2,
            'mp_smtp_ace_log_data' => json_encode(['cart' => 'old', 'checkoutCompleted' => true]),
        ]);

        $this->helperMock->method('getACEData')->with($quote)->willReturn(['cart' => 'new']);
        $this->helperMock->expects($this->never())->method('getResourceQuote');
        $this->helperMock->expects($this->never())->method('sendRequestWithoutWaitResponse');

        $this->sut->execute($this->observerWithQuote($quote));
    }

    public function testExecuteLogsException(): void
    {
        $this->enableGate();
        $quote = new DataObject(['id' => 7, 'items_count' => 2]);
        $this->helperMock->method('getACEData')->willThrowException(new \Exception('fail'));

        $this->loggerMock->expects($this->once())->method('critical')->with('fail');

        $this->sut->execute($this->observerWithQuote($quote));
    }
}

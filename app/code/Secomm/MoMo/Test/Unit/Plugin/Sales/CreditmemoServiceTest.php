<?php
/**
 * Unit tests for the creditmemo linkage backfill plugin (MOMO-02).
 *
 * The plugin runs after CreditmemoService::refund() and only writes
 * creditmemo_id onto the matching MoMo refund row; every non-applicable
 * case (offline refund, non-MoMo method, no recorded requestId, missing
 * row) must be a strict no-op.
 *
 * @author    Secomm Teams
 * @copyright Copyright (c) 2026 Secomm (https://www.secomm.vn)
 * @package   Secomm_MoMo
 */
declare(strict_types=1);

namespace Secomm\MoMo\Test\Unit\Plugin\Sales;

use Magento\Sales\Api\Data\CreditmemoInterface;
use Magento\Sales\Api\CreditmemoManagementInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\Payment;
use PHPUnit\Framework\TestCase;
use Secomm\MoMo\Api\Data\RefundRequestInterface;
use Secomm\MoMo\Api\RefundRequestRepositoryInterface;
use Secomm\MoMo\Gateway\Response\RefundHandler;
use Secomm\MoMo\Plugin\Sales\CreditmemoService;

class CreditmemoServiceTest extends TestCase
{
    /**
     * @var RefundRequestRepositoryInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    private $repository;

    /**
     * @var CreditmemoService
     */
    private CreditmemoService $plugin;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->repository = $this->createMock(RefundRequestRepositoryInterface::class);
        $this->plugin = new CreditmemoService($this->repository);
    }

    /**
     * @param array $options
     * @return CreditmemoInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    private function creditmemo(array $options = [])
    {
        $options += ['offline' => false, 'id' => 55, 'requestId' => 'MOMO-RQ-1', 'method' => 'momo_payment'];
        $payment = $this->createMock(Payment::class);
        $payment->method('getMethod')->willReturn($options['method']);
        $payment->method('getAdditionalInformation')->willReturnCallback(
            fn (string $key) => $key === RefundHandler::KEY_REFUND_REQUEST_ID ? $options['requestId'] : null
        );
        $order = $this->createMock(OrderInterface::class);
        $order->method('getPayment')->willReturn($payment);
        $creditmemo = $this->createMock(Creditmemo::class);
        $creditmemo->method('getEntityId')->willReturn($options['id']);
        $creditmemo->method('getOrder')->willReturn($order);

        return $creditmemo;
    }

    /**
     * @return void
     */
    public function testBackfillsCreditmemoIdOnMatchingRow(): void
    {
        $row = $this->createMock(RefundRequestInterface::class);
        $row->method('getEntityId')->willReturn(9);
        $this->repository->method('getByRequestId')->with('MOMO-RQ-1')->willReturn($row);
        $this->repository->expects($this->once())->method('markCreditmemo')->with(9, 55);

        $result = $this->createMock(CreditmemoInterface::class);
        $this->assertSame($result, $this->plugin->afterRefund(
            $this->createMock(CreditmemoManagementInterface::class),
            $result,
            $this->creditmemo()
        ));
    }

    /**
     * Offline refunds never went through MoMo — strict no-op.
     *
     * @return void
     */
    public function testOfflineRefundIsNoop(): void
    {
        $this->repository->expects($this->never())->method('getByRequestId');
        $this->repository->expects($this->never())->method('markCreditmemo');

        $result = $this->createMock(CreditmemoInterface::class);
        $this->assertSame($result, $this->plugin->afterRefund(
            $this->createMock(CreditmemoManagementInterface::class),
            $result,
            $this->creditmemo(['offline' => true]),
            true
        ));
    }

    /**
     * Non-MoMo payments are out of scope.
     *
     * @return void
     */
    public function testNonMomoPaymentIsNoop(): void
    {
        $this->repository->expects($this->never())->method('getByRequestId');
        $this->repository->expects($this->never())->method('markCreditmemo');

        $result = $this->createMock(CreditmemoInterface::class);
        $this->assertSame($result, $this->plugin->afterRefund(
            $this->createMock(CreditmemoManagementInterface::class),
            $result,
            $this->creditmemo(['method' => 'checkmo'])
        ));
    }

    /**
     * A refund that never reached the gateway (no requestId recorded) has
     * no row to link.
     *
     * @return void
     */
    public function testMissingRequestIdIsNoop(): void
    {
        $this->repository->expects($this->never())->method('getByRequestId');
        $this->repository->expects($this->never())->method('markCreditmemo');

        $result = $this->createMock(CreditmemoInterface::class);
        $this->assertSame($result, $this->plugin->afterRefund(
            $this->createMock(CreditmemoManagementInterface::class),
            $result,
            $this->creditmemo(['requestId' => ''])
        ));
    }

    /**
     * No matching row (e.g. legacy payment) degrades to a no-op, never an
     * error on the admin refund path.
     *
     * @return void
     */
    public function testRowMissIsNoop(): void
    {
        $this->repository->method('getByRequestId')->willReturn(null);
        $this->repository->expects($this->never())->method('markCreditmemo');

        $result = $this->createMock(CreditmemoInterface::class);
        $this->assertSame($result, $this->plugin->afterRefund(
            $this->createMock(CreditmemoManagementInterface::class),
            $result,
            $this->creditmemo()
        ));
    }
}

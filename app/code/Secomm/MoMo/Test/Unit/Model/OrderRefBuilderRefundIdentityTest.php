<?php
/**
 * Unit tests for the refund identity minting on OrderRefBuilder (MOMO-02).
 *
 * The provider contract caps orderId/requestId at 50 chars and requires the
 * refund's orderId to differ from the original purchase orderId — the minted
 * identity must satisfy both while keeping the human-traceable prefix.
 *
 * @author    Secomm Teams
 * @copyright Copyright (c) 2026 Secomm (https://www.secomm.vn)
 * @package   Secomm_MoMo
 */
declare(strict_types=1);

namespace Secomm\MoMo\Test\Unit\Model;

use Magento\Framework\Stdlib\DateTime\DateTime;
use PHPUnit\Framework\TestCase;
use Secomm\MoMo\Model\OrderRefBuilder;

class OrderRefBuilderRefundIdentityTest extends TestCase
{
    /**
     * @return OrderRefBuilder
     */
    private function builder(): OrderRefBuilder
    {
        return new OrderRefBuilder($this->createMock(DateTime::class));
    }

    /**
     * @return void
     */
    public function testRefundOrderIdDiffersFromOrderRefAndKeepsPrefix(): void
    {
        $orderRef = 'MOMO2609180000-SLP-1';
        $refundOrderId = $this->builder()->buildRefundOrderId($orderRef);

        $this->assertNotSame($orderRef, $refundOrderId);
        $this->assertStringStartsWith($orderRef . '-RF', $refundOrderId);
    }

    /**
     * @return void
     */
    public function testRefundRequestIdDiffersFromRefundOrderId(): void
    {
        $orderRef = 'MOMO2609180000-SLP-1';
        $builder = $this->builder();
        $refundOrderId = $builder->buildRefundOrderId($orderRef);
        $requestId = $builder->buildRefundRequestId($orderRef);

        $this->assertNotSame($refundOrderId, $requestId);
        $this->assertStringStartsWith($orderRef . '-RQ', $requestId);
    }

    /**
     * Provider String(50) caps: even a maximum-length order ref (64 chars)
     * must yield identities of at most 49 chars.
     *
     * @return void
     */
    public function testLongOrderRefStaysWithinProviderLimits(): void
    {
        $orderRef = str_repeat('A', 64);
        $builder = $this->builder();

        $this->assertLessThanOrEqual(49, strlen($builder->buildRefundOrderId($orderRef)));
        $this->assertLessThanOrEqual(49, strlen($builder->buildRefundRequestId($orderRef)));
    }

    /**
     * Each call mints a fresh identity (a retried logical operation gets
     * its own provider identity).
     *
     * @return void
     */
    public function testIdentitiesAreUniquePerCall(): void
    {
        $builder = $this->builder();

        $this->assertNotSame(
            $builder->buildRefundOrderId('REF-1'),
            $builder->buildRefundOrderId('REF-1')
        );
        $this->assertNotSame(
            $builder->buildRefundRequestId('REF-1'),
            $builder->buildRefundRequestId('REF-1')
        );
    }
}

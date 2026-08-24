<?php
declare(strict_types=1);

namespace Secomm\AiCommerce\Test\Unit\Service\Inventory;

use Magento\InventoryCatalog\Model\GetStockIdForCurrentWebsite;
use Magento\InventorySalesApi\Api\AreProductsSalableInterface;
use Magento\InventorySalesApi\Api\Data\IsProductSalableResultInterface;
use Magento\InventorySalesApi\Api\IsProductSalableInterface;
use PHPUnit\Framework\TestCase;
use Secomm\AiCommerce\Service\Inventory\Availability;

class AvailabilityTest extends TestCase
{
    /**
     * @var IsProductSalableInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    private $isProductSalable;

    /**
     * @var AreProductsSalableInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    private $areProductsSalable;

    /**
     * @var Availability
     */
    private $availability;

    protected function setUp(): void
    {
        $this->isProductSalable = $this->createMock(IsProductSalableInterface::class);
        $this->areProductsSalable = $this->createMock(AreProductsSalableInterface::class);

        $getStockId = $this->createMock(GetStockIdForCurrentWebsite::class);
        $getStockId->method('execute')->willReturn(1);

        $this->availability = new Availability(
            $this->isProductSalable,
            $this->areProductsSalable,
            $getStockId
        );
    }

    public function testSingleStatusTrueMapsToInStock(): void
    {
        $this->isProductSalable->method('execute')->with('SKU1', 1)->willReturn(true);
        $this->assertSame('in_stock', $this->availability->getStatus('SKU1'));
    }

    public function testSingleStatusFalseMapsToOutOfStock(): void
    {
        $this->isProductSalable->method('execute')->willReturn(false);
        $this->assertSame('out_of_stock', $this->availability->getStatus('SKU1'));
    }

    public function testBatchUsesOneCallForAllSkus(): void
    {
        $this->areProductsSalable->expects($this->once())
            ->method('execute')
            ->with(['A', 'B', 'C'], 1)
            ->willReturn([
                $this->salableResult('A', true),
                $this->salableResult('B', false),
                $this->salableResult('C', true),
            ]);

        $statuses = $this->availability->getStatuses(['A', 'B', 'C']);

        $this->assertSame(
            ['A' => 'in_stock', 'B' => 'out_of_stock', 'C' => 'in_stock'],
            $statuses
        );
    }

    public function testBatchOutputContainsNoQuantities(): void
    {
        $this->areProductsSalable->method('execute')->willReturn([$this->salableResult('A', true)]);

        $statuses = $this->availability->getStatuses(['A']);

        foreach ($statuses as $status) {
            $this->assertIsString($status);
            $this->assertStringNotContainsString('qty', $status);
        }
    }

    public function testEmptyBatchIsNoop(): void
    {
        $this->areProductsSalable->expects($this->never())->method('execute');
        $this->assertSame([], $this->availability->getStatuses([]));
    }

    /**
     * Create a salability result mock.
     *
     * @param string $sku sku
     * @param bool $salable salability
     * @return IsProductSalableResultInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    private function salableResult(string $sku, bool $salable)
    {
        $result = $this->createMock(IsProductSalableResultInterface::class);
        $result->method('getSku')->willReturn($sku);
        $result->method('isSalable')->willReturn($salable);

        return $result;
    }
}

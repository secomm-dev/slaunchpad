<?php
declare(strict_types=1);

namespace Launchpad\MageplazaExtraFeeFix\Test\Unit\Plugin\Model\Total\Creditmemo;

use Launchpad\MageplazaExtraFeeFix\Plugin\Model\Total\Creditmemo\ExtraFeePlugin;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Creditmemo;
use Mageplaza\ExtraFee\Helper\Data as ExtraFeeHelper;
use Mageplaza\ExtraFee\Model\Total\Creditmemo\ExtraFee;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ExtraFeePluginTest extends TestCase
{
    private ExtraFeeHelper|MockObject $helper;
    private PriceCurrencyInterface|MockObject $priceCurrency;
    private Http|MockObject $request;
    private ExtraFeePlugin $plugin;

    protected function setUp(): void
    {
        $this->helper = $this->createMock(ExtraFeeHelper::class);
        $this->priceCurrency = $this->createMock(PriceCurrencyInterface::class);
        $this->request = $this->createMock(Http::class);

        $this->priceCurrency->method('round')->willReturnCallback(function ($val) {
            return round((float)$val, 2);
        });

        $this->plugin = new ExtraFeePlugin(
            $this->helper,
            $this->priceCurrency,
            $this->request
        );
    }

    public function testSanitizesFormattedStringWithComma(): void
    {
        $subject = $this->createMock(ExtraFee::class);
        $order = $this->createMock(Order::class);
        $creditmemo = $this->createMock(Creditmemo::class);

        $creditmemo->method('getOrder')->willReturn($order);
        $this->helper->method('getObjectExtraFeeTotals')->willReturn([
            [
                'code' => 'mp_extra_fee_rule_1_auto',
                'label' => 'Phí bảo hiểm hàng hóa',
                'value_incl_tax' => 10000.0,
                'rf' => 1,
                'apply_type' => 1
            ]
        ]);

        $this->request->method('getControllerName')->willReturn('order_creditmemo');
        $this->request->method('getActionName')->willReturn('save');

        // Form post value with comma
        $creditmemo->method('getData')->with('mp_extra_fee_rule_1_auto')->willReturn('10,000');

        $creditmemo->method('getGrandTotal')->willReturn(1675000.0);
        $creditmemo->method('getBaseGrandTotal')->willReturn(1675000.0);

        $creditmemo->expects($this->once())->method('setGrandTotal')->with(1685000.0);
        $creditmemo->expects($this->once())->method('setBaseGrandTotal')->with(1685000.0);
        $creditmemo->expects($this->once())->method('setData')->with('mp_extra_fee_rule_1_auto', 10000.0);

        $result = $this->plugin->aroundCollect(
            $subject,
            function () {},
            $creditmemo
        );

        $this->assertSame($subject, $result);
    }

    public function testThrowsExceptionWhenAmountExceedsMaxFee(): void
    {
        $subject = $this->createMock(ExtraFee::class);
        $order = $this->createMock(Order::class);
        $creditmemo = $this->createMock(Creditmemo::class);

        $creditmemo->method('getOrder')->willReturn($order);
        $this->helper->method('getObjectExtraFeeTotals')->willReturn([
            [
                'code' => 'mp_extra_fee_rule_1_auto',
                'label' => 'Phí bảo hiểm hàng hóa',
                'value_incl_tax' => 10000.0,
                'rf' => 1,
                'apply_type' => 1
            ]
        ]);

        $this->request->method('getControllerName')->willReturn('order_creditmemo');
        $this->request->method('getActionName')->willReturn('save');

        $creditmemo->method('getData')->with('mp_extra_fee_rule_1_auto')->willReturn('15,000');

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Maximum Phí bảo hiểm hàng hóa amount allowed to refund is: 10000');

        $this->plugin->aroundCollect(
            $subject,
            function () {},
            $creditmemo
        );
    }

    public function testHandlesZeroAmountCorrectly(): void
    {
        $subject = $this->createMock(ExtraFee::class);
        $order = $this->createMock(Order::class);
        $creditmemo = $this->createMock(Creditmemo::class);

        $creditmemo->method('getOrder')->willReturn($order);
        $this->helper->method('getObjectExtraFeeTotals')->willReturn([
            [
                'code' => 'mp_extra_fee_rule_1_auto',
                'label' => 'Phí bảo hiểm hàng hóa',
                'value_incl_tax' => 10000.0,
                'rf' => 1,
                'apply_type' => 1
            ]
        ]);

        $this->request->method('getControllerName')->willReturn('order_creditmemo');
        $this->request->method('getActionName')->willReturn('save');

        // Explicit 0 refund
        $creditmemo->method('getData')->with('mp_extra_fee_rule_1_auto')->willReturn('0');

        $creditmemo->method('getGrandTotal')->willReturn(1675000.0);
        $creditmemo->method('getBaseGrandTotal')->willReturn(1675000.0);

        $creditmemo->expects($this->once())->method('setGrandTotal')->with(1675000.0);
        $creditmemo->expects($this->once())->method('setBaseGrandTotal')->with(1675000.0);
        $creditmemo->expects($this->once())->method('setData')->with('mp_extra_fee_rule_1_auto', 0.0);

        $result = $this->plugin->aroundCollect(
            $subject,
            function () {},
            $creditmemo
        );

        $this->assertSame($subject, $result);
    }
}

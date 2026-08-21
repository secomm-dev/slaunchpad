<?php
declare(strict_types=1);

namespace Secomm\MageplazaExtraFeeFix\Test\Unit\Plugin\Controller\Product;

use Magento\Framework\App\Request\Http;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Mageplaza\ExtraFee\Controller\Product\ExtraFee;
use PHPUnit\Framework\TestCase;
use Secomm\MageplazaExtraFeeFix\Plugin\Controller\Product\ExtraFeePlugin;

class ExtraFeePluginTest extends TestCase
{
    /**
     * @var ExtraFeePlugin
     */
    private $plugin;

    /**
     * @var Http|\PHPUnit\Framework\MockObject\MockObject
     */
    private $request;

    protected function setUp(): void
    {
        $this->plugin  = (new ObjectManager($this))->getObject(ExtraFeePlugin::class);
        $this->request = $this->createMock(Http::class);
    }

    private function subject(): ExtraFee
    {
        $subject = $this->createMock(ExtraFee::class);
        $subject->method('getRequest')->willReturn($this->request);

        return $subject;
    }

    public function testMissingSuperAttributeIsNormalizedToEmptyArray(): void
    {
        $this->request->method('getParam')->with('super_attribute')->willReturn(null);
        $this->request->expects($this->once())->method('setParam')->with('super_attribute', []);

        $this->plugin->beforeExecute($this->subject());
    }

    public function testNullSuperAttributeIsNormalizedToEmptyArray(): void
    {
        $this->request->method('getParam')->with('super_attribute')->willReturn(null);
        $this->request->expects($this->once())->method('setParam')->with('super_attribute', []);

        $this->plugin->beforeExecute($this->subject());
    }

    public function testEmptyArrayIsLeftUnchanged(): void
    {
        $this->request->method('getParam')->with('super_attribute')->willReturn([]);
        $this->request->expects($this->never())->method('setParam');

        $this->plugin->beforeExecute($this->subject());
    }

    /**
     * @return array
     */
    public function validSelectionProvider(): array
    {
        return [
            'int value'    => [[93 => 12]],
            'string value' => [[93 => '12']],
            'multiple'     => [[93 => 12, 142 => 55]],
        ];
    }

    /**
     * @dataProvider validSelectionProvider
     * @param array $superAttribute
     */
    public function testValidConfigurableSelectionsRemainUnchanged(array $superAttribute): void
    {
        $this->request->method('getParam')->with('super_attribute')->willReturn($superAttribute);
        $this->request->expects($this->never())->method('setParam');

        $this->plugin->beforeExecute($this->subject());
    }
}

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

namespace Mageplaza\Smtp\Test\Unit\Block\Adminhtml\System\Config;

use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\TestFramework\Unit\Helper\MockCreationTrait;
use Mageplaza\Smtp\Block\Adminhtml\System\Config\Button;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

#[CoversClass(Button::class)]
class ButtonTest extends TestCase
{
    use MockCreationTrait;

    // ── getHtmlId()/unsScope() are declared vs magic on AbstractElement's DataObject
    // chain (rules/unit-testing.md "Magic getXxx/setXxx accessors") — mixed in one list.
    private function createElementMock(): AbstractElement&MockObject
    {
        return $this->createPartialMockWithReflection(
            AbstractElement::class,
            ['getHtmlId', 'unsScope', 'getOriginalData']
        );
    }


    public function testRenderUnsetsScopeAndDelegatesToParentRenderChain(): void
    {
        $element = $this->createElementMock();
        $element->method('getHtmlId')->willReturn('id_x');
        $element->expects($this->once())->method('unsScope');

        // _getElementHtml is stubbed out — its own behaviour is covered separately below;
        // Field::render() (real, inherited) is left untouched to prove Button::render()
        // actually delegates to it after unsetting scope.
        $button = $this->getMockBuilder(Button::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['_getElementHtml'])
            ->getMock();
        $button->method('_getElementHtml')->willReturn('<element-html>');

        $result = $button->render($element);

        $this->assertStringContainsString('<element-html>', $result);
    }


    public function testGetElementHtmlBuildsAddDataPayloadAndReturnsToHtmlOutput(): void
    {
        $element = $this->createElementMock();
        $element->method('getOriginalData')->willReturn([
            'button_label' => 'Test',
            'button_url'   => 'adminhtml/x/y',
        ]);
        $element->method('getHtmlId')->willReturn('id_x');

        $button = $this->getMockBuilder(Button::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getUrl', 'addData', '_toHtml'])
            ->getMock();
        $button->expects($this->once())
            ->method('getUrl')
            ->with('adminhtml/x/y', ['_current' => true])
            ->willReturn('https://example.com/adminhtml/x/y');
        $button->expects($this->once())
            ->method('addData')
            ->with([
                'button_label' => 'Test',
                'button_url'   => 'https://example.com/adminhtml/x/y',
                'html_id'      => 'id_x',
            ]);
        $button->method('_toHtml')->willReturn('<button-html>');

        $method = new ReflectionMethod(Button::class, '_getElementHtml');
        $result = $method->invoke($button, $element);

        $this->assertSame('<button-html>', $result);
    }
}

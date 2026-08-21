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

namespace Mageplaza\Smtp\Test\Unit\Adminhtml\System\Config;

use Magento\Framework\Data\Form\Element\AbstractElement;
use Mageplaza\Smtp\Block\Adminhtml\System\Config\DatePicker;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(DatePicker::class)]
class DatePickerTest extends TestCase
{
    // setElement()/getElement() resolve via DataObject::__call (vendor/magento/framework/DataObject.php:394-425)
    // — left real since they only touch in-memory data storage. toHtml() is declared on
    // AbstractBlock (vendor/magento/framework/View/Element/AbstractBlock.php:666) and
    // _decorateRowHtml() is declared/protected on Field (vendor/magento/module-config/Block/System/Config/Form/Field.php:223);
    // both are stubbed to avoid the framework rendering/event pipeline.
    private function createSut(array $onlyMethods = []): DatePicker&MockObject
    {
        return $this->getMockBuilder(DatePicker::class)
            ->disableOriginalConstructor()
            ->onlyMethods($onlyMethods)
            ->getMock();
    }


    public function testGetFieldNameDelegatesToElementGetName(): void
    {
        $element = $this->createMock(AbstractElement::class);
        $element->method('getName')->willReturn('my_field');

        $this->assertSame('my_field', $this->createSut()->getFieldName($element));
    }


    public function testRenderSetsElementAndReturnsDecoratedToHtmlOutput(): void
    {
        $element = $this->createMock(AbstractElement::class);

        $sut = $this->createSut(['toHtml', '_decorateRowHtml']);
        $sut->expects($this->once())->method('toHtml')->willReturn('<div>rendered</div>');
        $sut->expects($this->once())
            ->method('_decorateRowHtml')
            ->with($element, '<div>rendered</div>')
            ->willReturn('<tr>decorated</tr>');

        $result = $sut->render($element);

        $this->assertSame('<tr>decorated</tr>', $result);
        $this->assertSame($element, $sut->getElement());
    }
}

<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Launchpad\MageplazaTableRate\Test\Unit\Block\Adminhtml\Method\Edit;

use Magento\Backend\Block\Widget\Tabs;
use Magento\Framework\Registry;
use Magento\Framework\View\LayoutInterface;
use Mageplaza\TableRateShipping\Model\Method;
use Mageplaza\TableRateShipping\Model\RegistryConstants;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Magento\Framework\DataObject;
use Magento\Framework\View\Element\Text;
use Magento\Backend\Model\Auth\Session;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Json\EncoderInterface;
use Magento\Backend\Block\Template\Context as TemplateContext;
use Magento\Framework\View\Element\Context as ElementContext;
use Launchpad\MageplazaTableRate\Block\Adminhtml\Method\Edit\MethodTabs;

/**
 * TASK-RT50KH regression — the Fallback Settings tab MUST land in the template snapshot.
 *
 * Defect this locks: core `Widget\Tabs::_beforeToHtml()` ends with `assign('tabs', $this->_tabs)`;
 * adding the fallback tab AFTER `parent::_beforeToHtml()` mutated `$_tabs` but never the
 * assigned snapshot, so the tab silently disappeared from the method edit page.
 */
class MethodTabsTest extends TestCase
{
    private Registry&MockObject $registry;

    private LayoutInterface&MockObject $layout;

    private MethodTabs $tabs;

    private ElementContext&MockObject $elementContext;

    protected function setUp(): void
    {
        $this->registry = $this->createMock(Registry::class);
        $this->layout = $this->createMock(LayoutInterface::class);
        // Shared element context for child Text blocks: null-safe mocks cover the
        // AbstractBlock constructor + toHtml event dispatch path.
        $this->elementContext = $this->createMock(ElementContext::class);
        $this->elementContext->method('getLayout')->willReturn($this->layout);
        $this->elementContext->method('getRequest')->willReturn($this->createMock(RequestInterface::class));
        $this->elementContext->method('getEventManager')->willReturn(
            $this->createMock(\Magento\Framework\Event\ManagerInterface::class)
        );
        $this->tabs = $this->tabsWithStubbedDeps();
    }

    /**
     * Real MethodTabs instance with constructor bypassed; the protected collaborator
     * properties (registry, auth session, request, layout) are injected via reflection so
     * the full `_beforeToHtml` parent chain can run without a full backend context.
     */
    private function tabsWithStubbedDeps(): MethodTabs
    {
        $this->layout->method('getChildName')->willReturnCallback(
            static fn (string $parent, string $alias): string => in_array($alias, ['launchpad', 'main', 'labels', 'rate'], true)
                ? 'mptablerate_method_edit_tabs.' . $alias
                : ''
        );
        $this->layout->method('getBlock')->willReturnCallback(
            fn (string $name): ?object => in_array($name, [
                'mptablerate_method_edit_tabs.launchpad',
                'mptablerate_method_edit_tabs.main',
                'mptablerate_method_edit_tabs.labels',
                'mptablerate_method_edit_tabs.rate',
            ], true) ? $this->childBlock($name) : null
        );
        $tabs = $this->getMockBuilder(MethodTabs::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getRequest', 'getNameInLayout'])
            ->getMock();
        $request = $this->createMock(HttpRequest::class);
        $request->method('getParam')->willReturn(null);
        $tabs->method('getRequest')->willReturn($request);
        $tabs->method('getNameInLayout')->willReturn('mptablerate_method_edit_tabs');

        $method = $this->createMock(Method::class);
        $method->method('getId')->willReturn(7);
        $this->registry->method('registry')
            ->with(RegistryConstants::METHOD)
            ->willReturn($method);

        $this->setProp($tabs, '_coreRegistry', $this->registry);
        $this->setProp($tabs, '_authSession', $this->createMock(Session::class));
        $this->setProp($tabs, '_layout', $this->layout);

        return $tabs;
    }

    private function setProp(object $target, string $property, mixed $value): void
    {
        $class = get_class($target);
        while ($class) {
            try {
                $ref = new \ReflectionProperty($class, $property);
                $ref->setAccessible(true);
                $ref->setValue($target, $value);

                return;
            } catch (\ReflectionException) {
                $class = get_parent_class($class) ?: null;
            }
        }
        // Undeclared (dynamic) property fallback — Mageplaza sets _coreRegistry in the
        // constructor without declaring it.
        $target->{$property} = $value;
    }

    private function childBlock(string $html): Text
    {
        $block = new Text($this->elementContext);
        $block->setText($html);

        return $block;
    }

    /** The launchpad child must produce a tab entry in the TEMPLATE snapshot (assigned 'tabs'). */
    public function testLaunchpadTabIsPresentInTheAssignedTemplateData(): void
    {
        $this->tabs->setNameInLayout('mptablerate_method_edit_tabs');
        $ref = new \ReflectionMethod($this->tabs, '_beforeToHtml');
        $ref->setAccessible(true);
        $ref->invoke($this->tabs);

        // Template::assign stores tabs in _viewVars ($tabs for tabs.phtml).
        $viewVars = (function (): array {
            $ref = new \ReflectionProperty(\Magento\Framework\View\Element\Template::class, '_viewVars');
            $ref->setAccessible(true);

            return $ref->getValue($this->tabs);
        })();
        $assigned = (array) ($viewVars['tabs'] ?? []);

        $this->assertArrayHasKey('main', $assigned);
        $this->assertArrayHasKey('labels', $assigned);
        $this->assertArrayHasKey('rate', $assigned);
        $this->assertArrayHasKey('launchpad', $assigned, 'The launchpad tab must reach the template snapshot');
        $this->assertSame('Fallback Settings', (string) $assigned['launchpad']->getLabel());
    }

    /** Edit of a saved method: Fallback Settings renders AFTER Shipping Rates. */
    public function testLaunchpadTabIsOrderedAfterShippingRates(): void
    {
        $this->tabs->setNameInLayout('mptablerate_method_edit_tabs');
        $ref = new \ReflectionMethod($this->tabs, '_beforeToHtml');
        $ref->setAccessible(true);
        $ref->invoke($this->tabs);

        $viewVars2 = (function (): array {
            $ref = new \ReflectionProperty(\Magento\Framework\View\Element\Template::class, '_viewVars');
            $ref->setAccessible(true);

            return $ref->getValue($this->tabs);
        })();
        $order = array_keys((array) ($viewVars2['tabs'] ?? []));

        $this->assertGreaterThan(
            array_search('rate', $order, true),
            array_search('launchpad', $order, true),
            'Fallback Settings must render after Shipping Rates'
        );
    }
}

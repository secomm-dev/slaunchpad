<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Test\Unit\View\Adminhtml;

use PHPUnit\Framework\TestCase;

/**
 * TASK-PWHG0V pre-review fix pack — locks the REAL `sales_shipment_view` layout wiring for the
 * GHN shipment actions block: block class + template attributes, the formkey child, and the
 * resolved on-disk template paths. The pre-review Critical defect (formkey template declared as
 * `Magento_Backend::widget/formkey.phtml`, which does not exist → template-not-found crash on
 * the real Shipment View) is exactly what this test catches — a direct `$block->setTemplate()
 * ->toHtml()` smoke bypasses layout XML and missed it.
 */
class SalesShipmentViewLayoutTest extends TestCase
{
    private const LAYOUT_FILE = __DIR__ . '/../../../../view/adminhtml/layout/sales_shipment_view.xml';

    public function testActionsBlockIsWiredWithFormkeyChild(): void
    {
        $xml = simplexml_load_file(self::LAYOUT_FILE);
        $this->assertNotFalse($xml, 'layout file must be valid XML');

        $blocks = $xml->xpath('//block[@name="secomm_ghn.shipment.view.actions"]');
        $this->assertCount(1, $blocks, 'exactly one actions block expected');
        /** @var \SimpleXMLElement $actions */
        $actions = $blocks[0];
        $this->assertSame(
            'Secomm_Ghn::shipment/actions.phtml',
            (string) $actions['template'],
            'actions block must use the module template'
        );

        $children = $actions->xpath('block[@as="formkey"]');
        $this->assertCount(1, $children, 'formkey child expected');
        /** @var \SimpleXMLElement $formkey */
        $formkey = $children[0];
        $this->assertSame(
            'Magento\Backend\Block\Admin\Formkey',
            (string) $formkey['class'],
            'formkey child must use the Magento Backend Admin Formkey block'
        );
        $this->assertSame(
            'Magento_Backend::admin/formkey.phtml',
            (string) $formkey['template'],
            'formkey child must use the REAL admin formkey template (pre-review Critical fix)'
        );
    }

    public function testWiredBlockClassesExist(): void
    {
        $xml = simplexml_load_file(self::LAYOUT_FILE);
        foreach ($xml->xpath('//block[@class]') as $block) {
            $this->assertTrue(
                class_exists((string) $block['class']),
                sprintf('layout block class %s must exist', (string) $block['class'])
            );
        }
    }

    public function testWiredTemplatesResolveOnDisk(): void
    {
        $xml = simplexml_load_file(self::LAYOUT_FILE);
        foreach ($xml->xpath('//block[@template]') as $block) {
            $template = (string) $block['template'];
            $path = $this->resolveTemplate($template);
            $this->assertNotNull($path, "template $template must resolve to a module view dir");
            $this->assertFileExists(
                $path,
                sprintf('template %s must exist on disk (template-not-found crashes the Shipment View)', $template)
            );
        }
    }

    /**
     * Minimal Magento template id resolution: `<Module>::<path>` → module view dir + path.
     */
    private function resolveTemplate(string $templateId): ?string
    {
        if (!str_contains($templateId, '::')) {
            return null;
        }

        [$module, $path] = explode('::', $templateId, 2);
        if (str_starts_with($module, 'Magento_')) {
            $dir = 'vendor/magento/module-' . strtolower(str_replace('_', '', substr($module, 8)));
        } else {
            $parts = explode('_', $module, 2);
            $dir = 'app/code/' . $parts[0] . '/' . ($parts[1] ?? '');
        }

        // layout file: <root>/app/code/Secomm/Ghn/view/adminhtml/layout/… → root is 8 dirnames up
        return dirname(realpath(self::LAYOUT_FILE), 8) . '/' . $dir . '/view/adminhtml/templates/' . $path;
    }
}

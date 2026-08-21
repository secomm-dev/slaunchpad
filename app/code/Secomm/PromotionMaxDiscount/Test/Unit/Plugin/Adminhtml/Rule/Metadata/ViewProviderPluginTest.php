<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\PromotionMaxDiscount\Test\Unit\Plugin\Adminhtml\Rule\Metadata;

use Magento\SalesRule\Model\Rule\Metadata\ValueProvider;
use PHPUnit\Framework\TestCase;
use Secomm\PromotionMaxDiscount\Plugin\Adminhtml\Rule\Metadata\ViewProviderPlugin;

/**
 * TASK-67GGPR AC-1/AC-4/AC-5 — field injected right after discount_amount,
 * exactly one new key, core keys untouched, no override when core ships it.
 */
class ViewProviderPluginTest extends TestCase
{
    /**
     * Minimal core-shaped actions.children
     *
     * @return array<string, array>
     */
    private function coreMeta(): array
    {
        return [
            'rule_information' => ['children' => ['is_active' => ['arguments' => ['data' => ['config' => ['options' => []]]]]]],
            'actions' => [
                'children' => [
                    'simple_action' => ['arguments' => ['data' => ['config' => ['options' => [['label' => 'x', 'value' => 'y']]]]]],
                    'discount_amount' => ['arguments' => ['data' => ['config' => ['value' => '0']]]],
                    'discount_qty' => ['arguments' => ['data' => ['config' => ['value' => '0']]]],
                    'apply_to_shipping' => ['arguments' => ['data' => ['config' => ['options' => []]]]],
                ],
            ],
        ];
    }

    public function testInjectsFieldRightAfterDiscountAmount(): void
    {
        $result = (new ViewProviderPlugin())
            ->afterGetMetadataValues($this->createMock(ValueProvider::class), $this->coreMeta());

        $names = array_keys($result['actions']['children']);
        $this->assertSame(
            ['simple_action', 'discount_amount', 'maximum_discount_amount', 'discount_qty', 'apply_to_shipping'],
            $names
        );
    }

    public function testFieldConfigMatchesSpec(): void
    {
        $result = (new ViewProviderPlugin())
            ->afterGetMetadataValues($this->createMock(ValueProvider::class), $this->coreMeta());

        $config = $result['actions']['children']['maximum_discount_amount']['arguments']['data']['config'];
        // componentType is REQUIRED: UiComponentFactory::mergeMetadataItem() only
        // materialises an unknown meta child when this key exists — without it the
        // whole sales rule form throws LocalizedException (pre-review C1)
        $this->assertSame('field', $config['componentType']);
        $this->assertSame('Secomm_PromotionMaxDiscount/js/form/element/max-discount-field', $config['component']);
        $this->assertSame('input', $config['formElement']);
        $this->assertSame('text', $config['dataType']);
        $this->assertSame('maximum_discount_amount', $config['dataScope']);
        $this->assertSame('sales_rule', $config['source']);
        $this->assertFalse($config['visible']);
        $this->assertTrue($config['validation']['validate-number']);
        $this->assertTrue($config['validation']['validate-zero-or-greater']);
        $this->assertSame('Maximum Discount Amount', (string) $config['label']);
        $this->assertStringContainsString('no limit', (string) $config['notice']);
    }

    public function testCoreKeysAreUntouched(): void
    {
        $before = $this->coreMeta();
        $result = (new ViewProviderPlugin())
            ->afterGetMetadataValues($this->createMock(ValueProvider::class), $before);

        foreach ($before['actions']['children'] as $name => $config) {
            $this->assertSame($config, $result['actions']['children'][$name], "core field $name changed");
        }
        $this->assertSame($before['rule_information'], $result['rule_information']);
    }

    public function testDoesNotOverrideWhenCoreShipsTheFieldNatively(): void
    {
        $meta = $this->coreMeta();
        $native = ['arguments' => ['data' => ['config' => ['label' => 'CORE']]]];
        $meta['actions']['children']['maximum_discount_amount'] = $native;

        $result = (new ViewProviderPlugin())
            ->afterGetMetadataValues($this->createMock(ValueProvider::class), $meta);

        $this->assertSame($native, $result['actions']['children']['maximum_discount_amount']);
    }

    public function testAppendsWhenDiscountAmountIsMissing(): void
    {
        $meta = $this->coreMeta();
        unset($meta['actions']['children']['discount_amount']);

        $result = (new ViewProviderPlugin())
            ->afterGetMetadataValues($this->createMock(ValueProvider::class), $meta);

        $this->assertArrayHasKey('maximum_discount_amount', $result['actions']['children']);
    }

    public function testNoActionsSectionIsPassedThrough(): void
    {
        $meta = ['rule_information' => ['children' => []]];

        $result = (new ViewProviderPlugin())
            ->afterGetMetadataValues($this->createMock(ValueProvider::class), $meta);

        $this->assertSame($meta, $result);
    }
}

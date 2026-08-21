<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\PromotionMaxDiscount\Test\Unit\Plugin\Adminhtml\Rule;

use Magento\SalesRule\Model\Rule;
use PHPUnit\Framework\TestCase;
use Secomm\PromotionMaxDiscount\Plugin\Adminhtml\Rule\LoadPostNormalizerPlugin;

/**
 * TASK-67GGPR AC-2/AC-3 — cleared visible input ('') becomes NULL before
 * loadPost; '0' stays 0; an absent key (disabled field) stays absent so the
 * stored cap survives an action switch.
 */
class LoadPostNormalizerPluginTest extends TestCase
{
    private LoadPostNormalizerPlugin $plugin;

    protected function setUp(): void
    {
        $this->plugin = new LoadPostNormalizerPlugin();
    }

    /**
     * @return Rule
     */
    private function rule(): Rule
    {
        return $this->createMock(Rule::class);
    }

    public function testEmptyStringBecomesNull(): void
    {
        [$data] = $this->plugin->beforeLoadPost($this->rule(), [
            'name' => 'R',
            'maximum_discount_amount' => '',
        ]);
        $this->assertNull($data['maximum_discount_amount']);
        $this->assertSame('R', $data['name']);
    }

    public function testZeroStaysZero(): void
    {
        [$data] = $this->plugin->beforeLoadPost($this->rule(), ['maximum_discount_amount' => '0']);
        $this->assertSame('0', $data['maximum_discount_amount']);
    }

    public function testValueStaysUntouched(): void
    {
        [$data] = $this->plugin->beforeLoadPost($this->rule(), ['maximum_discount_amount' => '50000']);
        $this->assertSame('50000', $data['maximum_discount_amount']);
    }

    public function testAbsentKeyStaysAbsent(): void
    {
        [$data] = $this->plugin->beforeLoadPost($this->rule(), ['name' => 'R']);
        $this->assertArrayNotHasKey('maximum_discount_amount', $data);
    }
}

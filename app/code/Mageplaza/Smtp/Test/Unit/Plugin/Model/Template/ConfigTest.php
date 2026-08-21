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

namespace Mageplaza\Smtp\Test\Unit\Plugin\Model\Template;

use Magento\Email\Model\Template\Config as EmailConfig;
use Mageplaza\Smtp\Plugin\Model\Template\Config;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(Config::class)]
class ConfigTest extends TestCase
{
    private EmailConfig&MockObject $subject;

    private Config $plugin;

    protected function setUp(): void
    {
        $this->subject = $this->createMock(EmailConfig::class);
        $this->plugin  = new Config();
    }

    public function testAfterGetAvailableTemplatesRemovesAbandonedCart(): void
    {
        $templates = [
            ['value' => 'template_a', 'label' => 'A'],
            ['value' => 'mpsmtp_abandoned_cart_email_templates', 'label' => 'ACE'],
            ['value' => 'template_b', 'label' => 'B'],
        ];

        $result = $this->plugin->afterGetAvailableTemplates($this->subject, $templates);

        $values = array_column($result, 'value');
        $this->assertNotContains('mpsmtp_abandoned_cart_email_templates', $values);
        $this->assertContains('template_a', $values);
        $this->assertContains('template_b', $values);
    }

    public function testAfterGetAvailableTemplatesRemovesAbandonedCartAtFirstPositionLeavesKeyGap(): void
    {
        $templates = [
            0 => ['value' => 'mpsmtp_abandoned_cart_email_templates', 'label' => 'ACE'],
            1 => ['value' => 'template_a', 'label' => 'A'],
        ];

        $result = $this->plugin->afterGetAvailableTemplates($this->subject, $templates);

        // unset() removes the matched key without reindexing — the gap at 0 stays.
        $this->assertArrayNotHasKey(0, $result);
        $this->assertSame(['value' => 'template_a', 'label' => 'A'], $result[1]);
    }

    public function testAfterGetAvailableTemplatesDropsFirstWhenTargetAbsent(): void
    {
        // array_search() returns false when the target is absent; unset() coerces the
        // false key to index 0, so the first template is dropped even though it never
        // matched — pinned so a future refactor that changes this is noticed.
        $templates = [
            ['value' => 'template_a', 'label' => 'A'],
            ['value' => 'template_b', 'label' => 'B'],
        ];

        $result = $this->plugin->afterGetAvailableTemplates($this->subject, $templates);

        $this->assertSame(['template_b'], array_values(array_column($result, 'value')));
    }
}

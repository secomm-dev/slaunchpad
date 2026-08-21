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

namespace Mageplaza\Smtp\Test\Unit\Model\Config\Source;

use Mageplaza\Smtp\Model\Config\Source\Newsletter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Newsletter::class)]
class NewsletterTest extends TestCase
{
    private Newsletter $model;

    protected function setUp(): void
    {
        $this->model = new Newsletter();
    }

    public function testToOptionArrayReturnsAllAndSubscribedOptions(): void
    {
        $result = $this->model->toOptionArray();

        $this->assertCount(2, $result);
        $this->assertSame(Newsletter::ALL, $result[0]['value']);
        $this->assertSame('All', (string) $result[0]['label']);
        $this->assertSame(Newsletter::SUBSCRIBED, $result[1]['value']);
        $this->assertSame('Only Subscribed', (string) $result[1]['label']);
    }
}

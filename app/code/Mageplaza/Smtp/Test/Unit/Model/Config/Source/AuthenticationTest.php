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

use Mageplaza\Smtp\Model\Config\Source\Authentication;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Authentication::class)]
class AuthenticationTest extends TestCase
{
    private Authentication $model;

    protected function setUp(): void
    {
        $this->model = new Authentication();
    }

    public function testToOptionArrayReturnsFiveOptions(): void
    {
        $this->assertCount(5, $this->model->toOptionArray());
    }

    public function testToOptionArrayEveryOptionHasValueAndLabelKeys(): void
    {
        foreach ($this->model->toOptionArray() as $option) {
            $this->assertArrayHasKey('value', $option);
            $this->assertArrayHasKey('label', $option);
        }
    }

    public function testToOptionArrayValuesMatchDeclaredOrder(): void
    {
        $values = array_column($this->model->toOptionArray(), 'value');

        $this->assertSame(['smtp', 'plain', 'login', 'crammd5', 'oauth2'], $values);
    }

    public function testToOptionArrayFirstOptionIsNone(): void
    {
        // assertEquals — the label is a Phrase object, not a scalar.
        $this->assertEquals(
            ['value' => 'smtp', 'label' => __('NONE')],
            $this->model->toOptionArray()[0]
        );
    }

    public function testToOptionArrayLastOptionIsOauth2(): void
    {
        // OAuth2 drives the Office365 Graph API flow — the exact label wording is load-bearing.
        $this->assertEquals(
            ['value' => 'oauth2', 'label' => __('OAUTH2 (Office365)')],
            $this->model->toOptionArray()[4]
        );
    }
}

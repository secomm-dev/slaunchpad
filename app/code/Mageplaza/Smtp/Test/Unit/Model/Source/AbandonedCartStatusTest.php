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

namespace Mageplaza\Smtp\Test\Unit\Model\Source;

use Mageplaza\Smtp\Model\Source\AbandonedCartStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AbandonedCartStatus::class)]
class AbandonedCartStatusTest extends TestCase
{
    private AbandonedCartStatus $model;

    protected function setUp(): void
    {
        $this->model = new AbandonedCartStatus();
    }

    public function testConstantsMatchExpectedStatusValues(): void
    {
        $this->assertSame(1, AbandonedCartStatus::SENT);
        $this->assertSame(0, AbandonedCartStatus::WAIT_FOR_SEND);
    }

    public function testGetOptionArrayReturnsSentAndWaitForSendKeyedByStatus(): void
    {
        $options = AbandonedCartStatus::getOptionArray();

        $this->assertCount(2, $options);
        $this->assertArrayHasKey(AbandonedCartStatus::SENT, $options);
        $this->assertArrayHasKey(AbandonedCartStatus::WAIT_FOR_SEND, $options);
        $this->assertSame('Sent', (string) $options[AbandonedCartStatus::SENT]);
        $this->assertSame('Wait for send', (string) $options[AbandonedCartStatus::WAIT_FOR_SEND]);
    }

    public function testToOptionArrayTransformsOptionArrayIntoValueLabelPairs(): void
    {
        $result = $this->model->toOptionArray();

        $this->assertCount(2, $result);
        $this->assertSame(AbandonedCartStatus::SENT, $result[0]['value']);
        $this->assertSame('Sent', (string) $result[0]['label']);
        $this->assertSame(AbandonedCartStatus::WAIT_FOR_SEND, $result[1]['value']);
        $this->assertSame('Wait for send', (string) $result[1]['label']);
    }

    public function testToOptionArrayMirrorsGetOptionArray(): void
    {
        $source = AbandonedCartStatus::getOptionArray();
        $options = $this->model->toOptionArray();

        $this->assertSameSize($source, $options);

        foreach ($options as $option) {
            $this->assertArrayHasKey($option['value'], $source);
            $this->assertSame((string) $source[$option['value']], (string) $option['label']);
        }
    }
}

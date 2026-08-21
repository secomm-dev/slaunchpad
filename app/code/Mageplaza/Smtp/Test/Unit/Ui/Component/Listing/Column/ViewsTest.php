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

namespace Mageplaza\Smtp\Test\Unit\Ui\Component\Listing\Column;

use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Mageplaza\Smtp\Ui\Component\Listing\Column\Views;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(Views::class)]
class ViewsTest extends TestCase
{
    private ContextInterface&MockObject $context;
    private UiComponentFactory&MockObject $uiComponentFactory;
    private UrlInterface&MockObject $urlBuilder;

    protected function setUp(): void
    {
        $this->context = $this->createMock(ContextInterface::class);
        $this->uiComponentFactory = $this->createMock(UiComponentFactory::class);
        $this->urlBuilder = $this->createMock(UrlInterface::class);
    }

    private function createSut(): Views
    {
        return new Views(
            $this->context,
            $this->uiComponentFactory,
            $this->urlBuilder,
            [],
            ['name' => 'views']
        );
    }

    public function testPrepareDataSourceReturnsUnchangedWhenItemsKeyMissing(): void
    {
        $this->urlBuilder->expects($this->never())->method('getUrl');

        $dataSource = ['data' => ['totalRecords' => 0]];

        $this->assertSame($dataSource, $this->createSut()->prepareDataSource($dataSource));
    }

    public function testPrepareDataSourceReturnsUnchangedWhenDataSourceIsEmpty(): void
    {
        $this->urlBuilder->expects($this->never())->method('getUrl');

        $this->assertSame([], $this->createSut()->prepareDataSource([]));
    }

    public function testPrepareDataSourceSetsViewLinkForSingleItem(): void
    {
        $this->urlBuilder->method('getUrl')
            ->with('adminhtml/smtp_abandonedcart/view', ['id' => 3])
            ->willReturn('https://example.com/admin/smtp_abandonedcart/view/id/3/');

        $dataSource = ['data' => ['items' => [['entity_id' => 3]]]];

        $result = $this->createSut()->prepareDataSource($dataSource);

        // 'label' holds a Phrase object — assertSame would compare instance identity, not value.
        $this->assertEquals(
            [
                'view' => [
                    'label' => __('View'),
                    'href'  => 'https://example.com/admin/smtp_abandonedcart/view/id/3/',
                ],
            ],
            $result['data']['items'][0]['views']
        );
    }

    public function testPrepareDataSourceSetsViewLinkForEachItem(): void
    {
        $this->urlBuilder->method('getUrl')
            ->willReturnMap([
                ['adminhtml/smtp_abandonedcart/view', ['id' => 1], 'https://example.com/view/1'],
                ['adminhtml/smtp_abandonedcart/view', ['id' => 2], 'https://example.com/view/2'],
            ]);

        $dataSource = [
            'data' => [
                'items' => [
                    ['entity_id' => 1],
                    ['entity_id' => 2],
                ],
            ],
        ];

        $result = $this->createSut()->prepareDataSource($dataSource);

        $this->assertSame('https://example.com/view/1', $result['data']['items'][0]['views']['view']['href']);
        $this->assertSame('https://example.com/view/2', $result['data']['items'][1]['views']['view']['href']);
    }
}

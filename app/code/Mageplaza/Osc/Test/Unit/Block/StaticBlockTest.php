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
 * @category  Mageplaza
 * @package   Mageplaza_Osc
 * @copyright Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license   https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\Osc\Test\Unit\Block;

use Magento\Cms\Block\Block;
use Magento\Framework\View\Element\BlockInterface;
use Magento\Framework\View\Element\Template\Context;
use Magento\Framework\View\LayoutInterface;
use Mageplaza\Osc\Block\StaticBlock;
use Mageplaza\Osc\Helper\Data as OscHelper;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionException;

class StaticBlockTest extends TestCase
{
    /**
     * @var OscHelper|MockObject
     */
    private $oscHelperMock;

    /**
     * @var StaticBlock|MockObject
     */
    private $staticBock;

    /**
     * @var LoggerInterface|MockObject
     */
    private $loggerMock;

    /**
     * @var LayoutInterface|MockObject
     */
    private $layoutMock;

    /**
     * @var Context|MockObject $contextMock
     */
    private $contextMock;

    protected function setUp(): void
    {
        $this->contextMock = $this->getMockBuilder(Context::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->loggerMock = $this->getMockForAbstractClass(LoggerInterface::class);
        $this->contextMock->method('getLogger')->willReturn($this->loggerMock);

        $this->oscHelperMock = $this->getMockBuilder(OscHelper::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->staticBock = new StaticBlock(
            $this->contextMock,
            $this->oscHelperMock
        );
    }

    public function testGetStaticBockWithException()
    {
        $this->loggerMock->expects($this->once())->method('critical')->with('Layout must be initialized');
        $this->assertEquals([], $this->staticBock->getStaticBlock());
    }

    public function testGetStaticBlockWithDisableStaticBlock()
    {
        $this->layoutMock = $this->getMockForAbstractClass(LayoutInterface::class);
        $this->staticBock->setLayout($this->layoutMock);
        $this->oscHelperMock->expects($this->once())->method('isEnableStaticBlock')->willReturn(false);

        $this->staticBock->getStaticBlock();
    }

    /**
     * @return array
     */
    public function providerTestGetStaticBlock()
    {
        return [
            [
                [
                    [
                        'content' => 'test2',
                        'sortOrder' => 1
                    ],
                    [
                        'content' => 'test1',
                        'sortOrder' => 2
                    ]
                ],
                [
                    '_1590552277494_494' =>
                        [
                            'block' => '1',
                            'position' => '1',
                            'sort_order' => '2',
                        ],
                    '_1590552288160_160' =>
                        [
                            'block' => '2',
                            'position' => '1',
                            'sort_order' => '1',
                        ],
                ],
                'osc.static-block.success'
            ],
            [
                [
                    [
                        'content' => 'test2',
                        'sortOrder' => 1
                    ],
                    [
                        'content' => 'test1',
                        'sortOrder' => 2
                    ]
                ],
                [
                    '_1590552277494_494' =>
                        [
                            'block' => '1',
                            'position' => '2',
                            'sort_order' => '2',
                        ],
                    '_1590552288160_160' =>
                        [
                            'block' => '2',
                            'position' => '2',
                            'sort_order' => '1',
                        ],
                ],
                'osc.static-block.top'
            ],
            [
                [
                    [
                        'content' => 'test2',
                        'sortOrder' => 1
                    ],
                    [
                        'content' => 'test1',
                        'sortOrder' => 2
                    ]
                ],
                [
                    '_1590552277494_494' =>
                        [
                            'block' => '1',
                            'position' => '3',
                            'sort_order' => '2',
                        ],
                    '_1590552288160_160' =>
                        [
                            'block' => '2',
                            'position' => '3',
                            'sort_order' => '1',
                        ],
                ],
                'osc.static-block.bottom'
            ]
        ];
    }

    /**
     * @param array  $result
     * @param array  $staticBlockList
     * @param string $nameLayout
     *
     * @dataProvider providerTestGetStaticBlock
     * @throws       ReflectionException
     */
    public function testGetStaticBlock($result, $staticBlockList, $nameLayout)
    {
        $this->layoutMock = $this->getMockForAbstractClass(LayoutInterface::class);
        $this->staticBock->setLayout($this->layoutMock);
        $this->oscHelperMock->expects($this->once())->method('isEnableStaticBlock')->willReturn(true);
        $this->oscHelperMock->expects($this->once())->method('getStaticBlockList')->willReturn($staticBlockList);
        $blockInterfaceMock = $this->getMockBuilder(BlockInterface::class)
            ->setMethods(['setBlockId'])
            ->getMockForAbstractClass();

        $this->layoutMock->expects($this->exactly(2))
            ->method('createBlock')
            ->with(Block::class)->willReturn($blockInterfaceMock);
        $blockInterfaceMock->expects($this->exactly(2))
            ->method('setBlockId')
            ->withConsecutive([1], [2])
            ->willReturnSelf();
        $blockInterfaceMock->expects($this->exactly(2))
            ->method('toHtml')
            ->willReturnOnConsecutiveCalls('test1', 'test2');
        $this->staticBock->setNameInLayout($nameLayout);

        $this->assertEquals($result, $this->staticBock->getStaticBlock());
    }
}

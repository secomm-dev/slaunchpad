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

namespace Mageplaza\Osc\Test\Unit\Helper;

use Magento\Bundle\Block\Catalog\Product\View\Type\Bundle;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Block\Product\View\Options;
use Magento\Catalog\Helper\Image;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Configuration\Item\ItemResolverInterface;
use Magento\ConfigurableProduct\Block\Product\View\Type\Configurable;
use Magento\Downloadable\Block\Checkout\Cart\Item\Renderer;
use Magento\Framework\App\Area;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Registry;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Framework\View\Asset\File;
use Magento\Framework\View\Asset\Repository;
use Magento\Framework\View\ConfigInterface;
use Magento\Framework\View\Element\BlockInterface;
use Magento\Framework\View\Layout\BuilderFactory;
use Magento\Framework\View\Layout\BuilderInterface;
use Magento\Framework\View\Layout\ProcessorInterface;
use Magento\Framework\View\LayoutFactory;
use Magento\Framework\View\LayoutInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item as QuoteItem;
use Mageplaza\Osc\Helper\Item;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionException;

class ItemTest extends TestCase
{
    /**
     * @var LayoutInterface|MockObject
     */
    protected $layoutMock;

    /**
     * @var LayoutFactory|MockObject
     */
    protected $layoutFactoryMock;

    /**
     * @var BuilderFactory|MockObject
     */
    protected $builderFactoryMock;

    /**
     * @var Registry|MockObject
     */
    protected $registryMock;

    /**
     * @var Image|MockObject
     */
    private $catalogHelperMock;

    /**
     * @var ConfigInterface|MockObject
     */
    private $viewConfigMock;

    /**
     * @var Repository|MockObject
     */
    private $repositoryMock;

    /**
     * @var Item
     */
    private $helper;

    /**
     * @var MockObject
     */
    private $objectManagerMock;

    /**
     * @var ObjectManager
     */
    private $objectManager;

    /**
     * @var QuoteItem|MockObject
     */
    private $quoteItemMock;

    /**
     * @var Product|MockObject
     */
    private $productMock;

    /**
     * @var int
     */
    private $at = 0;

    protected function setUp(): void
    {
        $this->objectManager = new ObjectManager($this);
        $this->layoutFactoryMock = $this->getMockBuilder(LayoutFactory::class)
            ->disableOriginalConstructor()->getMock();
        $this->builderFactoryMock = $this->getMockBuilder(BuilderFactory::class)
            ->disableOriginalConstructor()->getMock();
        $this->registryMock = $this->getMockBuilder(Registry::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->catalogHelperMock = $this->getMockBuilder(Image::class)
            ->disableOriginalConstructor()
            ->getMock();

        $viewConfigMethods = get_class_methods(ConfigInterface::class);
        $viewConfigMethods[] = 'getMediaAttributes';
        $this->viewConfigMock = $this->getMockBuilder(ConfigInterface::class)
            ->setMethods($viewConfigMethods)
            ->getMockForAbstractClass();
        $this->objectManagerMock = $this->getMockForAbstractClass(ObjectManagerInterface::class);
        $this->repositoryMock = $this->getMockBuilder(Repository::class)
            ->disableOriginalConstructor()->getMock();

        $this->helper = $this->objectManager->getObject(
            Item::class,
            [
                'layoutFactory' => $this->layoutFactoryMock,
                'builderFactory' => $this->builderFactoryMock,
                'registry' => $this->registryMock,
                'catalogHelper' => $this->catalogHelperMock,
                'viewConfig' => $this->viewConfigMock,
                'repository' => $this->repositoryMock,
                'objectManager' => $this->objectManagerMock
            ]
        );
    }

    /**
     * @return array
     */
    public function providerGetItemOptionsConfigWithConfigurableProduct()
    {
        return [
            [
                [
                    'customOptions' => [
                        'template' => 'test',
                        'optionConfig' => []
                    ],
                    'configurableAttributes' => [
                        'template' => 'test',
                        'spConfig' => [],
                    ]
                ],
                1,
                true
            ],
            [
                [
                    'configurableAttributes' => [
                        'template' => 'test',
                        'spConfig' => [],
                    ]
                ],
                1,
                false
            ],
            [
                [
                    'configurableAttributes' => [
                        'template' => 'test',
                        'spConfig' => [],
                    ]
                ],
                new DataObject(),
                false
            ]
        ];
    }

    /**
     * @param array      $result
     * @param int|object $item
     * @param boolean    $options
     *
     * @dataProvider providerGetItemOptionsConfigWithConfigurableProduct
     * @throws       ReflectionException
     */
    public function testGetItemOptionsConfigWithConfigurableProduct($result, $item, $options)
    {
        $quoteMock = $this->initGetItemOptionsConfig($item, $options);

        $this->quoteItemMock->expects($this->once())->method('getProductType')->willReturn('configurable');

        $this->getLayoutMock();
        /**
         * @var MockObject $configurableMock
         */
        $configurableMock = $this->getMockBuilder(Configurable::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->layoutMock->expects($this->at($this->at))->method('getBlock')
            ->with('mposc.configurable.options')
            ->willReturn($configurableMock);
        $configurableMock->expects($this->once())->method('unsetData')->with('allow_products');
        $configurableMock->expects($this->once())->method('addData')
            ->with(
                [
                'product' => $this->productMock,
                'quote_item' => $this->quoteItemMock
                ]
            );

        $configurableMock->expects($this->once())->method('toHtml')->willReturn('test');
        $configurableMock->expects($this->once())->method('getJsonConfig')->willReturn([]);

        $this->assertEquals(
            $result,
            $this->helper->getItemOptionsConfig($quoteMock, is_object($item) ? $this->quoteItemMock : $item)
        );
    }

    /**
     * @return array
     */
    public function providerGetItemOptionsConfigWithDownloadableProduct()
    {
        return [
            [
                [
                    'customOptions' => [
                        'template' => '',
                        'optionConfig' => null
                    ],
                ],
                1,
                false,
                false
            ],
            [
                [
                    'customOptions' => [
                        'template' => 'test',
                        'optionConfig' => null,
                    ]
                ],
                new DataObject(),
                false,
                true
            ]
        ];
    }

    /**
     * @param array      $result
     * @param int|object $item
     * @param array      $options
     * @param boolean    $isLinksPurchased
     *
     * @dataProvider providerGetItemOptionsConfigWithDownloadableProduct
     * @throws       ReflectionException
     */
    public function testGetItemOptionsConfigWithDownloadableConfig($result, $item, $options, $isLinksPurchased)
    {
        $quoteMock = $this->initGetItemOptionsConfig($item, $options);

        $this->quoteItemMock->expects($this->once())->method('getProductType')->willReturn('downloadable');

        $this->getLayoutMock();
        /**
         * @var MockObject $downloadable
         */
        $downloadable = $this->getMockBuilder(Renderer::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->layoutMock->expects($this->at($this->at))->method('getBlock')
            ->with('mposc.downloadable.options')
            ->willReturn($downloadable);

        $downloadable->expects($this->once())->method('setData')
            ->with(
                [
                'product' => $this->productMock,
                'item' => $this->quoteItemMock
                ]
            );

        $this->productMock->expects($this->once())
            ->method('getLinksPurchasedSeparately')
            ->willReturn($isLinksPurchased);

        if ($isLinksPurchased) {
            $downloadable->expects($this->once())->method('toHtml')->willReturn('test');
        }

        $this->assertEquals(
            $result,
            $this->helper->getItemOptionsConfig($quoteMock, is_object($item) ? $this->quoteItemMock : $item)
        );
    }

    /**
     * @return array
     */
    public function providerGetItemOptionsConfigWithBundleProduct()
    {
        return [
            [
                [
                    'customOptions' => [
                        'template' => 'test',
                        'optionConfig' => []
                    ],
                ],
                1,
                false
            ],
            [
                [
                    'customOptions' => [
                        'template' => 'test',
                        'optionConfig' => [],
                    ]
                ],
                new DataObject(),
                false
            ]
        ];
    }

    /**
     * @param array      $result
     * @param int|object $item
     * @param array      $options
     *
     * @dataProvider providerGetItemOptionsConfigWithBundleProduct
     * @throws       ReflectionException
     */
    public function testGetItemOptionsConfigWithBundleConfig($result, $item, $options)
    {
        $quoteMock = $this->initGetItemOptionsConfig($item, $options);

        $this->quoteItemMock->expects($this->once())->method('getProductType')->willReturn('bundle');
        $this->getLayoutMock();
        /**
         * @var MockObject $bundleMock
         */
        $bundleMock = $this->getMockBuilder(Bundle::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->layoutMock->expects($this->at($this->at))->method('getBlock')
            ->with('mposc.bundle.options')
            ->willReturn($bundleMock);

        $bundleMock->expects($this->once())->method('setData')
            ->with(
                [
                'product' => $this->productMock,
                'item' => $this->quoteItemMock
                ]
            );
        $bundleMock->expects($this->once())->method('getOptions')->with(true);

        $bundleMock->expects($this->once())->method('toHtml')->willReturn('test');
        $bundleMock->expects($this->once())->method('getJsonConfig')->willReturn([]);

        $this->assertEquals(
            $result,
            $this->helper->getItemOptionsConfig($quoteMock, is_object($item) ? $this->quoteItemMock : $item)
        );
    }

    /**
     * @param int|object $item
     * @param boolean    $options
     *
     * @return Quote
     * @throws ReflectionException
     */
    public function initGetItemOptionsConfig($item, $options)
    {
        $this->quoteItemMock = $this->getMockBuilder(QuoteItem::class)
            ->disableOriginalConstructor()
            ->getMock();

        /**
         * @var Quote $quoteMock
         */
        $quoteMock = $this->getMockBuilder(Quote::class)->disableOriginalConstructor()->getMock();
        if (!is_object($item)) {
            $quoteMock->expects($this->once())
                ->method('getItemById')
                ->with($item)
                ->willReturn($this->quoteItemMock);
        }

        $productMethods = get_class_methods(Product::class);
        $productMethods[] = 'setPreconfiguredValues';
        $productMethods[] = 'getLinksPurchasedSeparately';
        $this->productMock = $this->getMockBuilder(Product::class)
            ->setMethods($productMethods)
            ->disableOriginalConstructor()
            ->getMock();

        $this->quoteItemMock->expects($this->atLeastOnce())->method('getProduct')->willReturn($this->productMock);
        $buyRequest = new DataObject(['qty' => 1]);
        $processBuyRequestData = new DataObject(['qty' => 1]);
        $this->quoteItemMock->expects($this->once())->method('getBuyRequest')->willReturn($buyRequest);
        $this->productMock->expects($this->once())->method('processBuyRequest')->willReturn($processBuyRequestData);
        $this->productMock->expects($this->once())->method('setPreconfiguredValues')->with($processBuyRequestData);
        $this->registryMock->expects($this->once())->method('unregister')->with('current_product');
        $this->registryMock->expects($this->once())->method('register')->with('current_product', $this->productMock);

        $this->productMock->expects($this->once())->method('getOptions')->willReturn($options);
        $this->at = 2;
        if ($options) {
            $this->getLayoutMock();
            $optionsMock = $this->getMockBuilder(Options::class)->disableOriginalConstructor()->getMock();
            $this->layoutMock->expects($this->at($this->at))
                ->method('getBlock')
                ->with('mposc.product.options')
                ->willReturn($optionsMock);
            $this->at++;
            $optionsMock->expects($this->once())->method('setProduct')->willReturn($this->productMock);
            $optionsMock->expects($this->once())->method('toHtml')->willReturn('test');
            $optionsMock->expects($this->once())->method('getJsonConfig')->willReturn([]);
        }

        return $quoteMock;
    }

    /**
     * @return LayoutInterface|MockObject
     * @throws ReflectionException
     */
    public function getLayoutMock()
    {
        if (!$this->layoutMock) {
            $this->layoutMock = $this->getMockForAbstractClass(LayoutInterface::class);
            $this->layoutFactoryMock->expects($this->once())->method('create')->willReturn($this->layoutMock);
            $builderMock = $this->getMockForAbstractClass(BuilderInterface::class);
            $this->builderFactoryMock->expects($this->once())
                ->method('create')
                ->with(BuilderFactory::TYPE_LAYOUT, ['layout' => $this->layoutMock])
                ->willReturn($builderMock);

            $processorMock = $this->getMockForAbstractClass(ProcessorInterface::class);

            $this->layoutMock->expects($this->once())->method('getUpdate')->willReturn($processorMock);
            $processorMock->expects($this->atLeastOnce())
                ->method('addHandle')
                ->with(['default', 'onestepcheckout_product_config'])
                ->willReturnSelf();

            $blockMock = $this->getMockBuilder(BlockInterface::class)
                ->setMethods(['setData'])
                ->getMockForAbstractClass();
            $this->layoutMock->expects($this->once())->method('getAllBlocks')->willReturn([$blockMock]);
            $blockMock->expects($this->once())->method('setData')->with('area', Area::AREA_FRONTEND)->willReturnSelf();
        }

        return $this->layoutMock;
    }

    /**
     * @return array
     */
    public function providerTestGetItemImages()
    {
        $attributes = [
            'type' => 'thumbnail',
            'width' => 55,
            'height' => 55,
        ];

        return [
            [
                [
                    'src' => 'asset_url',
                    'width' => '11',
                    'height' => '22',
                    'alt' => 'test'
                ],
                true,
                '2.3.1',
                $attributes,
                $attributes,
                'webapi_rest',
            ],
            [
                [
                    'src' => 'asset_url',
                    'width' => '11',
                    'height' => '22',
                    'alt' => 'test'
                ],
                false,
                '2.1.1',
                $attributes,
                $attributes,
                'webapi_rest',
            ],
            [
                [
                    'src' => '',
                    'width' => '11',
                    'height' => '22',
                    'alt' => 'test'
                ],
                false,
                '2.1.1',
                [],
                [
                    'type' => 'thumbnail',
                    'width' => 75,
                    'height' => 75,
                ],
                '',
            ]
        ];
    }

    /**
     * @param array   $result
     * @param boolean $isValidVersion
     * @param string  $version
     * @param array   $attributes
     * @param array   $attributesInit
     * @param string  $url
     *
     * @dataProvider providerTestGetItemImages
     * @throws       LocalizedException
     * @throws       ReflectionException
     */
    public function testGetItemImages($result, $isValidVersion, $version, $attributes, $attributesInit, $url)
    {
        /**
         * @var QuoteItem|MockObject $item
         */
        $item = $this->getMockBuilder(QuoteItem::class)
            ->disableOriginalConstructor()
            ->getMock();
        $productMetaMock = $this->getMockForAbstractClass(ProductMetadataInterface::class);
        $this->objectManagerMock->expects($this->at(0))->method('get')
            ->with(ProductMetadataInterface::class)
            ->willReturn($productMetaMock);
        $productMetaMock->expects($this->once())->method('getVersion')->willReturn($version);
        $productMock = $this->getMockForAbstractClass(ProductInterface::class);
        if ($isValidVersion) {
            $itemResolver = $this->getMockForAbstractClass(ItemResolverInterface::class);
            $this->objectManagerMock->expects($this->at(1))->method('get')
                ->with(ItemResolverInterface::class)
                ->willReturn($itemResolver);

            $itemResolver->expects($this->once())->method('getFinalProduct')->willReturn($productMock);
        } else {
            $item->expects($this->once())->method('getProduct')->willReturn($productMock);
        }

        $this->viewConfigMock->expects($this->once())
            ->method('getViewConfig')
            ->with(['area' => Area::AREA_FRONTEND])
            ->willReturnSelf();
        $mediaId = 'mini_cart_product_thumbnail';
        $this->viewConfigMock->expects($this->once())
            ->method('getMediaAttributes')
            ->with('Magento_Catalog', Image::MEDIA_TYPE_CONFIG_NODE, $mediaId)
            ->willReturn($attributes);
        $this->catalogHelperMock->expects($this->once())
            ->method('init')
            ->with($productMock, $mediaId, $attributesInit)
            ->willReturnSelf();
        $this->catalogHelperMock->expects($this->once())->method('getUrl')->willReturn($url);

        if ($url) {
            $placeholder = 'test';
            $this->catalogHelperMock->expects($this->once())->method('getPlaceholder')->willReturn($placeholder);
            $fileMock = $this->getMockBuilder(File::class)->disableOriginalConstructor()->getMock();
            $this->repositoryMock->expects($this->once())
                ->method('createAsset')
                ->with($placeholder, ['area' => Area::AREA_FRONTEND])
                ->willReturn($fileMock);
            $fileMock->expects($this->once())->method('getUrl')->willReturn('asset_url');
        }

        $this->catalogHelperMock->expects($this->once())->method('getWidth')->willReturn('11');
        $this->catalogHelperMock->expects($this->once())->method('getHeight')->willReturn('22');
        $this->catalogHelperMock->expects($this->once())->method('getLabel')->willReturn('test');

        $this->assertEquals($result, $this->helper->getItemImages($item));
    }
}

<?php
/**
 * Created by PhpStorm.
 * User: Admin
 * Date: 12/01/2019
 * Time: 14:29 PM
 */

namespace Mageplaza\ThankYouPage\Test\Unit\Block\Product;

use Magento\Catalog\Block\Product\AbstractProduct;
use Magento\Catalog\Block\Product\Context;
use Magento\Catalog\Model\Config;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Checkout\Model\Session;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Item;
use Mageplaza\ThankYouPage\Block\Product\CrossSell;
use Mageplaza\ThankYouPage\Helper\Data;
use Mageplaza\ThankYouPage\Model\Config\Source\Pagetype;
use Mageplaza\ThankYouPage\Model\Template;
use PHPUnit\Framework\TestCase;
use PHPUnit_Framework_MockObject_MockObject;

/**
 * Class CrossSellTest
 * @package Mageplaza\ThankYouPage\Test\Unit\Block\Product
 */
class CrossSellTest extends TestCase
{
    /**
     * @var Context|PHPUnit_Framework_MockObject_MockObject
     */
    private $context;

    /**
     * @var CollectionFactory|PHPUnit_Framework_MockObject_MockObject
     */
    private $productCollectionFactory;

    /**
     * @var Data|PHPUnit_Framework_MockObject_MockObject
     */
    private $helperData;

    /**
     * @var Session|PHPUnit_Framework_MockObject_MockObject
     */
    private $checkoutSession;

    /**
     * @var Visibility|PHPUnit_Framework_MockObject_MockObject
     */
    private $_productVisibility;

    /**
     * @var Config|PHPUnit_Framework_MockObject_MockObject
     */
    private $_catalogConfig;

    /**
     * @var CrossSell
     */
    private $object;

    protected function setUp()
    {
        $objectManager                  = new ObjectManager($this);
        $this->context                  = $this->getMockBuilder(Context::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->productCollectionFactory = $this->getMockBuilder(CollectionFactory::class)
            ->disableOriginalConstructor()
            ->setMethods(['create'])
            ->getMock();
        $this->helperData               = $this->getMockBuilder(Data::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->checkoutSession          = $this->getMockBuilder(Session::class)
            ->setMethods([])
            ->disableOriginalConstructor()
            ->getMock();
        $this->_productVisibility       = $this->getMockBuilder(Visibility::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->_catalogConfig = $this->getMockBuilder(Config::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->context->method('getCatalogConfig')->willReturn($this->_catalogConfig);

        $this->object = $objectManager->getObject(
            CrossSell::class,
            [
                'context'                  => $this->context,
                'productCollectionFactory' => $this->productCollectionFactory,
                'helperData'               => $this->helperData,
                'checkoutSession'          => $this->checkoutSession,
                'productVisibility'        => $this->_productVisibility
            ]
        );
    }

    public function testAdminInstance()
    {
        $this->assertInstanceOf(CrossSell::class, $this->object);
    }

    /**
     * @param $page
     * @param $enable
     * @param $productIds
     * @param $limit
     * @param $result
     *
     * @throws NoSuchEntityException
     * @dataProvider productProvide
     */
    public function testGetProductCollection($page, $enable, $productIds, $limit, $result)
    {
        $attribute = ['color' => 'red'];
        $template  = $this->getMockBuilder(Template::class)
            ->disableOriginalConstructor()
            ->setMethods(['getEnableProductSlider', 'getProductSliderLimit'])
            ->getMock();
        $this->helperData->method('getTemplateData')->with($page)->willReturn($template);
        $template->method('getEnableProductSlider')->willReturn($enable);
        $order = $this->getMockBuilder(Order::class)
            ->disableOriginalConstructor()
            ->getMock();
        $items = $this->getMockBuilder(Item::class)
            ->disableOriginalConstructor()
            ->setMethods(['getProduct'])
            ->getMock();
        $order->method('getAllItems')->willReturn([$items]);
        $this->checkoutSession->method('getLastRealOrder')->willReturn($order);
        $product = $this->getMockBuilder(Product::class)
            ->disableOriginalConstructor()
            ->setMethods(['getCrossSellProductIds'])
            ->getMock();
        $items->method('getProduct')->willReturn($product);
        $product->method('getCrossSellProductIds')->willReturn($productIds);
        $productCollection = $this->getMockBuilder(Collection::class)
            ->disableOriginalConstructor()
            ->getMock();
        $abstractProduct   = $this->getMockBuilder(AbstractProduct::class)
            ->disableOriginalConstructor()
            ->setMethods(['_addProductAttributesAndPrices'])
            ->getMock();
        $this->productCollectionFactory->method('create')->willReturn($productCollection);
        $this->_productVisibility->method('getVisibleInCatalogIds')->willReturn([2, 4]);
        $productCollection->method('addIdFilter')->with($productIds)->willReturnSelf();
        $productCollection->method('addStoreFilter')->willReturnSelf();
        $productCollection->method('setVisibility')->with([2, 4])->willReturnSelf();
        $productCollection->method('addMinimalPrice')->willReturnSelf();
        $productCollection->method('addFinalPrice')->willReturnSelf();
        $productCollection->method('addTaxPercents')->willReturnSelf();
        $productCollection->method('addAttributeToSelect')->with($attribute)->willReturnSelf();
        $productCollection->method('addUrlRewrite')->willReturnSelf();
        $abstractProduct->method('_addProductAttributesAndPrices')->with($productCollection)->willReturn($productCollection);
        $this->_catalogConfig->method('getProductAttributes')->willReturn($attribute);
        $template->method('getProductSliderLimit')->willReturn($limit);
        $productCollection->method('setPageSize')->with($limit)->willReturn($result);

        $this->assertEquals($result, $this->object->getProductCollection());
    }

    /**
     * @return array
     */
    public function productProvide()
    {
        $result     = [
            'id'        => 10,
            'url'       => 'bag',
            'price'     => 100,
            'attribute' => ['color' => 'red']
        ];
        $productIds = [12, 13, 14, 15];

        return [
            [Pagetype::ORDER, 1, $productIds, 1, $result],
            [Pagetype::ORDER, 1, [], 0, []],
            [Pagetype::ORDER, 0, $productIds, 1, []]
        ];
    }
}

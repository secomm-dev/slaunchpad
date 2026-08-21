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

namespace Mageplaza\Smtp\Test\Unit\Block;

use Magento\Catalog\Block\Product\Context;
use Magento\Catalog\Helper\Data as TaxHelper;
use Magento\Catalog\Model\Product;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Context as CustomerContext;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Customer\Model\SessionFactory as CustomerSessionFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Http\Context as HttpContext;
use Magento\Framework\App\Request\Http;
use Magento\Framework\DataObject;
use Magento\Framework\Escaper;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\Registry;
use Magento\Framework\UrlInterface;
use Magento\Quote\Model\Quote;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Mageplaza\Smtp\Block\Script;
use Mageplaza\Smtp\Helper\EmailMarketing;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(Script::class)]
class ScriptTest extends TestCase
{
    private Context&MockObject $context;
    private EmailMarketing&MockObject $helperEmailMarketing;
    private CheckoutSession&MockObject $checkoutSession;
    private CustomerSessionFactory&MockObject $customerSessionFactory;
    private Registry&MockObject $registry;
    private HttpContext&MockObject $httpContext;
    private TaxHelper&MockObject $taxHelper;
    private PriceCurrencyInterface&MockObject $priceCurrency;

    protected function setUp(): void
    {
        $this->context                = $this->createMock(Context::class);
        $this->helperEmailMarketing   = $this->createMock(EmailMarketing::class);
        $this->checkoutSession        = $this->createMock(CheckoutSession::class);
        $this->customerSessionFactory = $this->createMock(CustomerSessionFactory::class);
        $this->registry               = $this->createMock(Registry::class);
        $this->httpContext            = $this->createMock(HttpContext::class);
        $this->taxHelper              = $this->createMock(TaxHelper::class);
        $this->priceCurrency          = $this->createMock(PriceCurrencyInterface::class);
    }

    private function createSut(): Script
    {
        return new Script(
            $this->context,
            $this->helperEmailMarketing,
            $this->checkoutSession,
            $this->customerSessionFactory,
            $this->registry,
            $this->httpContext,
            $this->taxHelper,
            $this->priceCurrency
        );
    }


    public function testIsSuccessPageTrueForCheckoutSuccess(): void
    {
        $request = $this->createMock(Http::class);
        $request->method('getFullActionName')->willReturn('checkout_onepage_success');
        $this->context->method('getRequest')->willReturn($request);

        $this->assertTrue($this->createSut()->isSuccessPage());
    }

    public function testIsSuccessPageTrueForThankYouPage(): void
    {
        $request = $this->createMock(Http::class);
        $request->method('getFullActionName')->willReturn('mpthankyoupage_index_index');
        $this->context->method('getRequest')->willReturn($request);

        $this->assertTrue($this->createSut()->isSuccessPage());
    }

    public function testIsSuccessPageFalseForOtherPage(): void
    {
        $request = $this->createMock(Http::class);
        $request->method('getFullActionName')->willReturn('catalog_product_view');
        $this->context->method('getRequest')->willReturn($request);

        $this->assertFalse($this->createSut()->isSuccessPage());
    }


    public function testToHtmlReturnsEmptyStringWhenEmailMarketingDisabled(): void
    {
        $this->helperEmailMarketing->method('isEnableEmailMarketing')->willReturn(false);

        $this->assertSame('', $this->createSut()->toHtml());
    }

    public function testToHtmlDelegatesToParentWhenEmailMarketingEnabled(): void
    {
        $this->helperEmailMarketing->method('isEnableEmailMarketing')->willReturn(true);

        // AbstractBlock::toHtml() dispatches view_block_abstract_to_html_before/_after —
        // asserting the count proves the guard clause actually delegated to parent::toHtml().
        $eventManager = $this->createMock(ManagerInterface::class);
        $eventManager->expects($this->exactly(2))->method('dispatch');
        $this->context->method('getEventManager')->willReturn($eventManager);

        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturn(false);
        $this->context->method('getScopeConfig')->willReturn($scopeConfig);

        $this->assertSame('', $this->createSut()->toHtml());
    }


    public function testGetCurrencyCodeReadsCurrentStoreCurrency(): void
    {
        $currency = new DataObject(['code' => 'USD']); // getCode() is magic on DataObject
        $store = $this->createMock(Store::class);
        $store->method('getCurrentCurrency')->willReturn($currency);
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);
        $this->context->method('getStoreManager')->willReturn($storeManager);

        $this->assertSame('USD', $this->createSut()->getCurrencyCode());
    }


    public function testGetCustomerDataReturnsShippingAddressDataForGuest(): void
    {
        $this->httpContext->method('getValue')->with(CustomerContext::CONTEXT_AUTH)->willReturn(false);

        $shippingAddress = new DataObject(['email' => 'guest@example.com', 'firstname' => 'Jane', 'lastname' => 'Doe']);
        $quote = $this->createMock(Quote::class);
        $quote->method('getShippingAddress')->willReturn($shippingAddress);
        $this->checkoutSession->method('getQuote')->willReturn($quote);

        $this->assertSame(
            ['email' => 'guest@example.com', 'firstname' => 'Jane', 'lastname' => 'Doe'],
            $this->createSut()->getCustomerData()
        );
    }

    public function testGetCustomerDataReturnsEmptyStringsForMissingGuestFields(): void
    {
        $this->httpContext->method('getValue')->with(CustomerContext::CONTEXT_AUTH)->willReturn(false);

        $quote = $this->createMock(Quote::class);
        $quote->method('getShippingAddress')->willReturn(new DataObject());
        $this->checkoutSession->method('getQuote')->willReturn($quote);

        $this->assertSame(
            ['email' => '', 'firstname' => '', 'lastname' => ''],
            $this->createSut()->getCustomerData()
        );
    }

    public function testGetCustomerDataReturnsCustomerDataWhenLoggedIn(): void
    {
        $this->httpContext->method('getValue')->with(CustomerContext::CONTEXT_AUTH)->willReturn(true);

        $customer = new DataObject(['email' => 'member@example.com', 'firstname' => 'John', 'lastname' => 'Smith']);
        $session = $this->createMock(CustomerSession::class);
        $session->method('getCustomer')->willReturn($customer);
        $this->customerSessionFactory->method('create')->willReturn($session);

        $this->assertSame(
            ['email' => 'member@example.com', 'firstname' => 'John', 'lastname' => 'Smith'],
            $this->createSut()->getCustomerData()
        );
    }

    public function testGetCustomerDataReturnsEmptyStringsForMissingCustomerFields(): void
    {
        $this->httpContext->method('getValue')->with(CustomerContext::CONTEXT_AUTH)->willReturn(true);

        $session = $this->createMock(CustomerSession::class);
        $session->method('getCustomer')->willReturn(new DataObject());
        $this->customerSessionFactory->method('create')->willReturn($session);

        $this->assertSame(
            ['email' => '', 'firstname' => '', 'lastname' => ''],
            $this->createSut()->getCustomerData()
        );
    }


    public function testProductAbandonedReturnsFalseWhenNotOnProductPage(): void
    {
        $request = $this->createMock(Http::class);
        $request->method('getFullActionName')->willReturn('checkout_cart_index');
        $this->context->method('getRequest')->willReturn($request);

        $this->assertFalse($this->createSut()->productAbandoned());
    }

    public function testProductAbandonedReturnsProductDataOnProductPage(): void
    {
        $request = $this->createMock(Http::class);
        $request->method('getFullActionName')->willReturn('catalog_product_view');
        $this->context->method('getRequest')->willReturn($request);
        $this->context->method('getEscaper')->willReturn(new Escaper());

        $product = $this->createMock(Product::class);
        $product->method('getImage')->willReturn('/p/r/product.jpg');
        $product->method('getId')->willReturn(42);
        $product->method('getTypeId')->willReturn('simple');
        $product->method('getName')->willReturn('Test Product');
        $product->method('getProductUrl')->willReturn('https://example.com/test-product.html');
        $product->method('getFinalPrice')->willReturn(100.0);
        $this->registry->method('registry')->with('current_product')->willReturn($product);

        $store = $this->createMock(Store::class);
        $store->method('getBaseUrl')->with(UrlInterface::URL_TYPE_MEDIA)->willReturn('https://example.com/media/');
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);
        $this->context->method('getStoreManager')->willReturn($storeManager);

        // Echoes its input back so 'price' (no tax) and 'priceTax' (via taxHelper) stay distinguishable.
        $this->priceCurrency->method('convert')->willReturnArgument(0);
        $this->taxHelper->method('getTaxPrice')->willReturn(110.0);

        $this->assertSame(
            [
                'collections' => [],
                'id'          => 42,
                'image'       => 'https://example.com/media/catalog/product/p/r/product.jpg',
                'price'       => '100.00',
                'priceTax'    => '110.00',
                'productType' => 'simple',
                'tags'        => [],
                'title'       => 'Test Product',
                'url'         => 'https://example.com/test-product.html',
                'vendor'      => 'magento',
            ],
            $this->createSut()->productAbandoned()
        );
    }


    public function testGetPriceSimpleProductConvertsToPresentationCurrency(): void
    {
        $this->priceCurrency->method('convert')->with(100.0)->willReturn(120.0);

        $product = $this->createMock(Product::class);
        $product->method('getFinalPrice')->willReturn(100.0);
        $product->method('getTypeId')->willReturn('simple');

        $this->assertSame('120.00', $this->createSut()->getPrice($product));
    }

    public function testGetPriceConfigurableProductSkipsCurrencyConversion(): void
    {
        // convert() still runs as a side effect of the unconditional pre-computation, but its
        // result is discarded for configurable products — the decoy value proves the overwrite.
        $this->priceCurrency->method('convert')->willReturn(999.0);

        $product = $this->createMock(Product::class);
        $product->method('getFinalPrice')->willReturn(50.0);
        $product->method('getTypeId')->willReturn('configurable');

        $this->assertSame('50.00', $this->createSut()->getPrice($product));
    }

    public function testGetPriceSimpleProductWithTaxRoutesThroughTaxHelper(): void
    {
        $this->priceCurrency->method('convert')->willReturnArgument(0);
        $this->taxHelper->method('getTaxPrice')->willReturn(110.0);

        $product = $this->createMock(Product::class);
        $product->method('getFinalPrice')->willReturn(100.0);
        $product->method('getTypeId')->willReturn('simple');

        $this->assertSame('110.00', $this->createSut()->getPrice($product, true));
    }

    public function testGetPriceConfigurableProductWithTaxUsesRawTaxPrice(): void
    {
        $this->priceCurrency->method('convert')->willReturn(999.0); // decoy, discarded for configurable
        $this->taxHelper->method('getTaxPrice')->willReturn(75.5);

        $product = $this->createMock(Product::class);
        $product->method('getFinalPrice')->willReturn(70.0);
        $product->method('getTypeId')->willReturn('configurable');

        $this->assertSame('75.50', $this->createSut()->getPrice($product, true));
    }


    public function testGetIdentitiesReturnsCacheTag(): void
    {
        $this->assertSame([EmailMarketing::CACHE_TAG], $this->createSut()->getIdentities());
    }
}

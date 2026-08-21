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

namespace Mageplaza\Smtp\Test\Unit\Block\Adminhtml\AbandonedCart\Edit;

use ArrayIterator;
use Exception;
use Magento\Config\Model\Config\Source\Email\Identity;
use Magento\Config\Model\Config\Source\Email\Template;
use Magento\Customer\Api\Data\GroupInterface;
use Magento\Customer\Api\GroupRepositoryInterface;
use Magento\Customer\Model\Address\Config as AddressConfig;
use Magento\Customer\Block\Address\Renderer\RendererInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Escaper;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\Registry;
use Magento\Framework\TestFramework\Unit\Helper\MockCreationTrait;
use Magento\Framework\View\Element\Template as TemplateBlock;
use Magento\Framework\View\LayoutInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use Magento\Quote\Model\Quote\Item;
use Magento\Store\Model\Group as StoreGroup;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Store\Model\Website;
use Magento\Tax\Model\Config as TaxConfig;
use Mageplaza\Smtp\Block\Adminhtml\AbandonedCart\Edit\Form;
use Mageplaza\Smtp\Helper\EmailMarketing;
use Mageplaza\Smtp\Model\Log;
use Mageplaza\Smtp\Model\ResourceModel\Log\Collection as LogCollection;
use Mageplaza\Smtp\Model\ResourceModel\Log\CollectionFactory as LogCollectionFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(Form::class)]
class FormTest extends TestCase
{
    use MockCreationTrait;

    private Registry&MockObject $registry;
    private AddressConfig&MockObject $addressConfig;
    private PriceCurrencyInterface&MockObject $priceCurrency;
    private Identity&MockObject $emailIdentity;
    private Template&MockObject $emailTemplate;
    private TaxConfig&MockObject $taxConfig;
    private LogCollectionFactory&MockObject $logCollectionFactory;
    private EmailMarketing&MockObject $helperEmailMarketing;
    private GroupRepositoryInterface&MockObject $groupRepository;
    private StoreManagerInterface&MockObject $storeManager;

    protected function setUp(): void
    {
        $this->registry             = $this->createMock(Registry::class);
        $this->addressConfig        = $this->createMock(AddressConfig::class);
        $this->priceCurrency        = $this->createMock(PriceCurrencyInterface::class);
        $this->emailIdentity        = $this->createMock(Identity::class);
        $this->emailTemplate        = $this->getMockBuilder(Template::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['toOptionArray'])
            ->getMock();
        $this->taxConfig            = $this->createMock(TaxConfig::class);
        $this->logCollectionFactory = $this->createPartialMock(LogCollectionFactory::class, ['create']);
        $this->helperEmailMarketing = $this->createMock(EmailMarketing::class);
        $this->groupRepository      = $this->createMock(GroupRepositoryInterface::class);
        $this->storeManager         = $this->createMock(StoreManagerInterface::class);
    }

    // Widget\Form::__construct() falls back to ObjectManager::getInstance() for its optional
    // ElementCreator arg, never bootstrapped in the unit test framework — disabled constructor + reflection instead.
    private function createSut(array $mockedMethods = []): Form&MockObject
    {
        $form = $this->getMockBuilder(Form::class)
            ->disableOriginalConstructor()
            ->onlyMethods($mockedMethods)
            ->getMock();

        $this->addPropertyValue($form, [
            'addressConfig'        => $this->addressConfig,
            'priceCurrency'        => $this->priceCurrency,
            'emailIdentity'        => $this->emailIdentity,
            'emailTemplate'        => $this->emailTemplate,
            'taxConfig'            => $this->taxConfig,
            'logCollectionFactory' => $this->logCollectionFactory,
            'helperEmailMarketing' => $this->helperEmailMarketing,
            'groupRepository'      => $this->groupRepository,
        ], Form::class);
        $this->addPropertyValue($form, ['_coreRegistry' => $this->registry]);
        $this->addPropertyValue($form, ['_storeManager' => $this->storeManager]);
        $this->addPropertyValue($form, ['_escaper' => new Escaper()]);

        return $form;
    }

    // getSubtotal()/Address subtotal magic accessor is not declared on Quote\Address
    // (rules/unit-testing.md — Magic Method Registry).
    private function createAddressMock(array $magicMethods): Address&MockObject
    {
        return $this->createPartialMockWithReflection(Address::class, $magicMethods);
    }


    public function testDisplayCartSubtotalInclTaxDelegatesToTaxConfig(): void
    {
        $this->taxConfig->expects($this->once())
            ->method('displayCartSubtotalInclTax')
            ->with(1)
            ->willReturn(true);

        $this->assertTrue($this->createSut()->displayCartSubtotalInclTax(1));
    }

    public function testDisplayCartSubtotalExclTaxDelegatesToTaxConfig(): void
    {
        $this->taxConfig->expects($this->once())
            ->method('displayCartSubtotalExclTax')
            ->with(1)
            ->willReturn(false);

        $this->assertFalse($this->createSut()->displayCartSubtotalExclTax(1));
    }

    public function testDisplayCartSubtotalBothDelegatesToTaxConfig(): void
    {
        $this->taxConfig->expects($this->once())
            ->method('displayCartSubtotalBoth')
            ->with(1)
            ->willReturn(true);

        $this->assertTrue($this->createSut()->displayCartSubtotalBoth(1));
    }


    public function testGetHelperEmailMarketingReturnsInjectedInstance(): void
    {
        $this->assertSame($this->helperEmailMarketing, $this->createSut()->getHelperEmailMarketing());
    }


    public function testGetSubtotalUsesShippingAddressWhenNonVirtualExclTax(): void
    {
        $address = $this->createAddressMock(['getSubtotal']);
        $address->method('getSubtotal')->willReturn(50.0);

        $quote = $this->createMock(Quote::class);
        $quote->method('isVirtual')->willReturn(false);
        $quote->method('getShippingAddress')->willReturn($address);
        $quote->method('getStoreId')->willReturn(1);

        $this->priceCurrency->expects($this->once())
            ->method('format')
            ->with(50.0, true, PriceCurrencyInterface::DEFAULT_PRECISION, 1)
            ->willReturn('$50.00');

        $this->assertSame('$50.00', $this->createSut()->getSubtotal($quote));
    }

    public function testGetSubtotalUsesBillingAddressWhenVirtualInclTax(): void
    {
        $address = $this->createAddressMock(['getSubtotalInclTax']);
        $address->method('getSubtotalInclTax')->willReturn(60.0);

        $quote = $this->createMock(Quote::class);
        $quote->method('isVirtual')->willReturn(true);
        $quote->method('getBillingAddress')->willReturn($address);
        $quote->method('getStoreId')->willReturn(2);

        $this->priceCurrency->expects($this->once())
            ->method('format')
            ->with(60.0, true, PriceCurrencyInterface::DEFAULT_PRECISION, 2)
            ->willReturn('$60.00');

        $this->assertSame('$60.00', $this->createSut()->getSubtotal($quote, true));
    }


    public function testGetModelReturnsRegisteredAbandonedCart(): void
    {
        $quote = $this->createMock(Quote::class);
        $this->registry->method('registry')->with('abandonedCart')->willReturn($quote);

        $this->assertSame($quote, $this->createSut()->getModel());
    }


    public function testGetSenderOptionsBuildsOptionHtml(): void
    {
        $this->emailIdentity->method('toOptionArray')->willReturn([
            ['value' => 'a', 'label' => 'A'],
        ]);

        $this->assertSame('<option value="a">A</option>', $this->createSut()->getSenderOptions());
    }


    public function testGetSentDateLogsReturnsEmptyStringWhenIdsEmpty(): void
    {
        $this->logCollectionFactory->expects($this->never())->method('create');

        $this->assertSame('', $this->createSut()->getSentDateLogs(''));
    }

    public function testGetSentDateLogsBuildsHtmlFromMatchingLogs(): void
    {
        $log1 = $this->createPartialMockWithReflection(Log::class, ['getCreatedAt']);
        $log1->method('getCreatedAt')->willReturn('2024-01-01 10:00:00');
        $log2 = $this->createPartialMockWithReflection(Log::class, ['getCreatedAt']);
        $log2->method('getCreatedAt')->willReturn('2024-01-02 10:00:00');

        $collection = $this->createMock(LogCollection::class);
        $collection->expects($this->once())
            ->method('addFieldToFilter')
            ->with('id', ['in' => '1,2'])
            ->willReturnSelf();
        $collection->method('getSize')->willReturn(2);
        $collection->method('getIterator')->willReturn(new ArrayIterator([$log1, $log2]));

        $this->logCollectionFactory->method('create')->willReturn($collection);

        $sut = $this->createSut(['formatDate']);
        $sut->method('formatDate')->willReturnCallback(
            static fn (string $date): string => 'fmt(' . $date . ')'
        );

        $this->assertSame(
            'fmt(2024-01-01 10:00:00)</br>fmt(2024-01-02 10:00:00)</br>',
            $sut->getSentDateLogs('1,2')
        );
    }

    public function testGetSentDateLogsReturnsEmptyStringWhenCollectionEmpty(): void
    {
        $collection = $this->createMock(LogCollection::class);
        $collection->method('addFieldToFilter')->willReturnSelf();
        $collection->method('getSize')->willReturn(0);
        $collection->expects($this->never())->method('getIterator');

        $this->logCollectionFactory->method('create')->willReturn($collection);

        $this->assertSame('', $this->createSut()->getSentDateLogs('99'));
    }


    public function testCreateOptionsBuildsHtmlForEachOption(): void
    {
        $result = $this->createSut()->createOptions([
            ['value' => 'a', 'label' => 'A'],
            ['value' => 'b', 'label' => 'B'],
        ]);

        $this->assertSame('<option value="a">A</option><option value="b">B</option>', $result);
    }

    public function testCreateOptionsReturnsEmptyStringForEmptyArray(): void
    {
        $this->assertSame('', $this->createSut()->createOptions([]));
    }


    public function testGetEmailTemplateOptionsSetsPathThenDelegates(): void
    {
        $this->emailTemplate->expects($this->once())
            ->method('toOptionArray')
            ->willReturn([['value' => 'tpl', 'label' => 'Tpl']]);

        $result = $this->createSut()->getEmailTemplateOptions();

        // setPath() is magic (DataObject::__call) — createMock() cannot configure it,
        // so verify via the real getPath() readback instead of ->expects() on setPath.
        $this->assertSame('mpsmtp_abandoned_cart_email_templates', $this->emailTemplate->getPath());
        $this->assertSame('<option value="tpl">Tpl</option>', $result);
    }


    public function testGetQuoteReturnsRegisteredQuote(): void
    {
        $quote = $this->createMock(Quote::class);
        $this->registry->method('registry')->with('quote')->willReturn($quote);

        $this->assertSame($quote, $this->createSut()->getQuote());
    }


    public function testGetItemUnitPriceHtmlDelegatesToNamedBlock(): void
    {
        $this->assertSame(
            '<unit-price-html>',
            $this->runBlockHtmlByNameDelegate('getItemUnitPriceHtml', 'item_unit_price', '<unit-price-html>')
        );
    }

    public function testGetItemRowTotalHtmlDelegatesToNamedBlock(): void
    {
        $this->assertSame(
            '<row-total-html>',
            $this->runBlockHtmlByNameDelegate('getItemRowTotalHtml', 'item_row_total', '<row-total-html>')
        );
    }

    public function testGetItemRowTotalWithDiscountHtmlDelegatesToNamedBlock(): void
    {
        $this->assertSame(
            '<row-total-discount-html>',
            $this->runBlockHtmlByNameDelegate(
                'getItemRowTotalWithDiscountHtml',
                'item_row_total_with_discount',
                '<row-total-discount-html>'
            )
        );
    }

    private function runBlockHtmlByNameDelegate(string $method, string $expectedBlockName, string $html): string
    {
        $item = $this->createMock(Item::class);

        $block = $this->createPartialMockWithReflection(TemplateBlock::class, ['setItem', 'toHtml']);
        $block->expects($this->once())->method('setItem')->with($item);
        $block->method('toHtml')->willReturn($html);

        $layout = $this->createMock(LayoutInterface::class);
        $layout->expects($this->once())->method('getBlock')->with($expectedBlockName)->willReturn($block);

        $sut = $this->createSut(['getLayout']);
        $sut->method('getLayout')->willReturn($layout);

        return $sut->$method($item);
    }


    public function testGetBlockHtmlByNameSetsItemAndReturnsToHtml(): void
    {
        $item = $this->createMock(Item::class);

        $block = $this->createPartialMockWithReflection(TemplateBlock::class, ['setItem', 'toHtml']);
        $block->expects($this->once())->method('setItem')->with($item);
        $block->method('toHtml')->willReturn('<html>');

        $layout = $this->createMock(LayoutInterface::class);
        $layout->method('getBlock')->with('custom_block')->willReturn($block);

        $sut = $this->createSut(['getLayout']);
        $sut->method('getLayout')->willReturn($layout);

        $this->assertSame('<html>', $sut->getBlockHtmlByName($item, 'custom_block'));
    }


    public function testFormatPriceDelegatesToPriceCurrency(): void
    {
        $this->priceCurrency->expects($this->once())
            ->method('format')
            ->with(10.5, true, PriceCurrencyInterface::DEFAULT_PRECISION, 1)
            ->willReturn('$10.50');

        $this->assertSame('$10.50', $this->createSut()->formatPrice(10.5, 1));
    }


    public function testGetStatusReturnsSentLabel(): void
    {
        $this->assertEquals(__('Sent'), $this->createSut()->getStatus(1));
    }

    public function testGetStatusReturnsWaitForSendLabel(): void
    {
        $this->assertEquals(__('Wait for send'), $this->createSut()->getStatus(0));
    }


    public function testIsSingleStoreModeDelegatesToStoreManager(): void
    {
        $this->storeManager->method('isSingleStoreMode')->willReturn(true);

        $this->assertTrue($this->createSut()->isSingleStoreMode());
    }

    public function testIsSingleStoreModeReturnsFalseWhenMultiStore(): void
    {
        $this->storeManager->method('isSingleStoreMode')->willReturn(false);

        $this->assertFalse($this->createSut()->isSingleStoreMode());
    }


    public function testGetStoreNameJoinsWebsiteGroupAndStoreNames(): void
    {
        $website = $this->createPartialMockWithReflection(Website::class, ['getName']);
        $website->method('getName')->willReturn('W');
        $group = $this->createPartialMockWithReflection(StoreGroup::class, ['getName']);
        $group->method('getName')->willReturn('G');

        $store = $this->createPartialMockWithReflection(Store::class, ['getWebsite', 'getGroup', 'getName']);
        $store->method('getWebsite')->willReturn($website);
        $store->method('getGroup')->willReturn($group);
        $store->method('getName')->willReturn('S');

        $quote = $this->createMock(Quote::class);
        $quote->method('getStoreId')->willReturn(1);

        $this->storeManager->expects($this->once())->method('getStore')->with(1)->willReturn($store);

        $this->assertSame('W<br/>G<br/>S', $this->createSut()->getStoreName($quote));
    }


    public function testGetFormattedAddressReturnsEmptyStringWhenNoCountryId(): void
    {
        $address = $this->createMock(Address::class);
        $address->method('getCountryId')->willReturn('');

        $this->assertSame('', $this->createSut()->getFormattedAddress($address, 1));
    }

    public function testGetFormattedAddressEscapesRenderedHtmlWithAllowedTags(): void
    {
        $address = $this->createMock(Address::class);
        $address->method('getCountryId')->willReturn('US');
        $address->method('getData')->willReturn([]);

        $renderer = $this->createMock(RendererInterface::class);
        $renderer->method('renderArray')->willReturn('<b>addr</b><script>bad()</script>');

        $formatType = new DataObject();
        $formatType->setRenderer($renderer);
        $this->addressConfig->method('getFormatByCode')->willReturn($formatType);

        $result = $this->createSut()->getFormattedAddress($address, 1);

        $this->assertStringContainsString('<b>addr</b>', $result);
        $this->assertStringNotContainsString('<script>', $result);
    }


    public function testFormatReturnsNullWhenNoFormatType(): void
    {
        $address = $this->createMock(Address::class);
        $this->addressConfig->expects($this->once())->method('setStore')->with(1);
        $this->addressConfig->method('getFormatByCode')->with('html')->willReturn(false);

        $this->assertNull($this->createSut()->format($address, 1));
    }

    public function testFormatReturnsNullWhenFormatTypeHasNoRenderer(): void
    {
        $address = $this->createMock(Address::class);
        $this->addressConfig->method('getFormatByCode')->willReturn(new DataObject());

        $this->assertNull($this->createSut()->format($address, 1));
    }

    public function testFormatRendersArrayWhenRendererPresent(): void
    {
        $address = $this->createMock(Address::class);
        $address->method('getData')->willReturn(['firstname' => 'John']);

        $renderer = $this->createMock(RendererInterface::class);
        $renderer->expects($this->once())
            ->method('renderArray')
            ->with(['firstname' => 'John'])
            ->willReturn('<p>addr</p>');

        $formatType = new DataObject();
        $formatType->setRenderer($renderer);
        $this->addressConfig->method('getFormatByCode')->willReturn($formatType);

        $this->assertSame('<p>addr</p>', $this->createSut()->format($address, 1));
    }


    public function testGetCustomerGroupNameReturnsCodeWhenFound(): void
    {
        $group = $this->createMock(GroupInterface::class);
        $group->method('getCode')->willReturn('General');
        $this->groupRepository->method('getById')->with(5)->willReturn($group);

        $this->assertSame('General', $this->createSut()->getCustomerGroupName(5));
    }

    public function testGetCustomerGroupNameReturnsEmptyStringWhenIdIsNull(): void
    {
        $this->groupRepository->expects($this->never())->method('getById');

        $this->assertSame('', $this->createSut()->getCustomerGroupName(null));
    }

    public function testGetCustomerGroupNameReturnsEmptyStringWhenRepositoryThrows(): void
    {
        $this->groupRepository->method('getById')->with(999)->willThrowException(new Exception('not found'));

        $this->assertSame('', $this->createSut()->getCustomerGroupName(999));
    }
}

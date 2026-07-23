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
 * @package     Mageplaza_ThankYouPage
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\ThankYouPage\Test\Unit\Block;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\Template\Context;
use Mageplaza\Faqs\Block\Category\View;
use Mageplaza\ThankYouPage\Block\Faq;
use Mageplaza\ThankYouPage\Helper\Data as ThankYouPageHelper;
use Mageplaza\ThankYouPage\Model\Config\Source\Pagetype;
use Mageplaza\ThankYouPage\Model\Template;
use PHPUnit\Framework\TestCase;
use PHPUnit_Framework_MockObject_MockObject;

/**
 * Class FaqTest
 * @package Mageplaza\ThankYouPage\Test\Unit\Block
 */
class FaqTest extends TestCase
{
    /**
     * @var Context|PHPUnit_Framework_MockObject_MockObject
     */
    private $context;

    /**
     * @var ThankYouPageHelper|PHPUnit_Framework_MockObject_MockObject
     */
    protected $thankyoupageHelper;

    /**
     * @var Faq
     */
    private $object;

    protected function setUp()
    {
        $this->thankyoupageHelper = $this->getMockBuilder(ThankYouPageHelper::class)->disableOriginalConstructor()->getMock();
        $this->context            = $this->getMockBuilder(Context::class)->disableOriginalConstructor()->getMock();
        $this->object             = new Faq($this->context, $this->thankyoupageHelper);
    }

    public function testAdminInstance()
    {
        $this->assertInstanceOf(Faq::class, $this->object);
    }

    /**
     * @param $enable
     * @param $cat
     * @param $page
     * @param $result
     *
     * @throws NoSuchEntityException
     * @dataProvider pageProvider
     */
    public function testGetOrderFaqs($enable, $cat, $page, $result)
    {
        $template = $this->getMockBuilder(Template::class)->disableOriginalConstructor()->getMock();
        $this->thankyoupageHelper->method('getTemplateData')->with($page)->willReturn($template);
        $template->method('getEnableFaq')->willReturn($enable);
        $template->method('getFaqCategory')->willReturn($cat);
        $faqView = $this->getMockBuilder(View::class)->disableOriginalConstructor()->getMock();
        $this->thankyoupageHelper->method('createObject')->with(View::class)->willReturn($faqView);

        $faqView->method('getArticleByCategory')->with($cat)->willReturn($result);

        $this->assertEquals($result, $this->object->getOrderFaqs());
    }

    /**
     * @return array
     */
    public function pageProvider()
    {
        $result = [
            "article_id"      => "1",
            "name"            => "tai sao",
            "author_name"     => "111",
            "author_email"    => "1111@domai.com",
            "article_content" => "<p>mai deo lam dc</p>",
            "url_key"         => "tai-sao",
            "category_id"     => "1",
        ];

        return [
            [true, 1, Pagetype::ORDER, $result],
            [true, null, Pagetype::ORDER, null],
            [false, null, Pagetype::ORDER, null]
        ];
    }
}

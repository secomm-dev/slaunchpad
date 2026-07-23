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

namespace Mageplaza\Osc\Test\Unit\Model;

use Exception;
use Magento\Checkout\Api\Data\ShippingInformationInterface;
use Magento\Customer\Api\AccountManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteIdMask;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Mageplaza\Osc\Api\CheckoutManagementInterface;
use Mageplaza\Osc\Model\GuestCheckoutManagement;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class GuestCheckoutManagementTest extends TestCase
{
    /**
     * @var QuoteIdMaskFactory|MockObject
     */
    private $quoteIdMaskFactoryMock;

    /**
     * @var CheckoutManagementInterface|MockObject
     */
    private $checkoutManagementMock;

    /**
     * @var CartRepositoryInterface|MockObject
     */
    private $cartRepositoryMock;

    /**
     * @var AccountManagementInterface|MockObject
     */
    private $accountManagementMock;

    /**
     * @var QuoteIdMask|MockObject
     */
    private $quoteIdMaskMock;

    /**
     * @var GuestCheckoutManagement
     */
    private $model;

    /**
     * @var string
     */
    private $cartId = 'rynMQaZ2wrERcFwNMRp0XB8wef8rNqqM';

    /**
     * @var int
     */
    private $quoteId = 1;

    protected function setUp(): void
    {
        $this->quoteIdMaskFactoryMock = $this->getMockBuilder(QuoteIdMaskFactory::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->checkoutManagementMock = $this->getMockBuilder(CheckoutManagementInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->cartRepositoryMock = $this->getMockBuilder(CartRepositoryInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->accountManagementMock = $this->getMockBuilder(AccountManagementInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $quoteIdMethods = get_class_methods(QuoteIdMask::class);
        $quoteIdMethods[] = 'getQuoteId';
        $this->quoteIdMaskMock = $this->getMockBuilder(QuoteIdMask::class)
            ->setMethods($quoteIdMethods)
            ->disableOriginalConstructor()
            ->getMock();

        $this->quoteIdMaskFactoryMock->expects($this->once())->method('create')->willReturn($this->quoteIdMaskMock);
        $this->quoteIdMaskMock->expects($this->once())->method('load')
            ->with($this->cartId, 'masked_id')
            ->willReturnSelf();
        $this->quoteIdMaskMock->expects($this->once())->method('getQuoteId')->willReturn($this->quoteId);

        $this->model = new GuestCheckoutManagement(
            $this->quoteIdMaskFactoryMock,
            $this->checkoutManagementMock,
            $this->cartRepositoryMock,
            $this->accountManagementMock
        );
    }

    public function testUpdateItemQty()
    {
        $itemId = 1;
        $itemQty = 2;

        $this->checkoutManagementMock->expects($this->once())
            ->method('updateItemQty')
            ->with($this->quoteId, $itemId, $itemQty);

        $this->model->updateItemQty($this->cartId, $itemId, $itemQty);
    }

    public function testRemoveItemById()
    {
        $itemId = 1;

        $this->checkoutManagementMock->expects($this->once())
            ->method('removeItemById')
            ->with($this->quoteId, $itemId);

        $this->model->removeItemById($this->cartId, $itemId);
    }

    public function testGetPaymentTotalInformation()
    {
        $this->checkoutManagementMock->expects($this->once())
            ->method('getPaymentTotalInformation')
            ->with($this->quoteId);

        $this->model->getPaymentTotalInformation($this->cartId);
    }

    public function testUpdateGiftWrap()
    {
        $isUseGiftWrap = false;
        $this->checkoutManagementMock->expects($this->once())
            ->method('updateGiftWrap')
            ->with($this->quoteId);

        $this->model->updateGiftWrap($this->cartId, $isUseGiftWrap);
    }

    /**
     * {@inheritDoc}
     */
    public function testSaveCheckoutInformation()
    {
        /**
         * @var ShippingInformationInterface $addressInformationMock
         */
        $addressInformationMock = $this->getMockBuilder(ShippingInformationInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->checkoutManagementMock->expects($this->once())
            ->method('saveCheckoutInformation')
            ->with(
                $this->quoteId,
                $addressInformationMock,
                [],
                []
            );

        $this->model->saveCheckoutInformation($this->cartId, $addressInformationMock);
    }

    /**
     * @return array
     */
    public function providerTestSaveEmailToQuote()
    {
        return [
            [
                true,
                false,
            ],
            [
                false,
                true
            ]
        ];
    }

    /**
     * @param boolean $result
     * @param boolean $isThrow
     *
     * @dataProvider providerTestSaveEmailToQuote
     */
    public function testSaveEmailToQuote($result, $isThrow)
    {
        $quoteMethods = get_class_methods(Quote::class);
        $quoteMethods[] = 'setCustomerEmail';
        $quoteMock = $this->getMockBuilder(Quote::class)
            ->setMethods($quoteMethods)
            ->disableOriginalConstructor()
            ->getMock();
        $this->cartRepositoryMock->expects($this->once())->method('getActive')
            ->with($this->quoteId)
            ->willReturn($quoteMock);
        $email = 'test@gmail.com';
        $quoteMock->expects($this->once())->method('setCustomerEmail')->with($email)->willReturnSelf();
        if ($isThrow) {
            $exception = new Exception();
            $this->cartRepositoryMock->expects($this->once())->method('save')
                ->with($quoteMock)
                ->willThrowException($exception);
        } else {
            $this->cartRepositoryMock->expects($this->once())->method('save')->with($quoteMock);
        }

        $this->assertEquals($result, $this->model->saveEmailToQuote($this->cartId, $email));
    }

    /**
     * {@inheritDoc}
     */
    public function testIsEmailAvailable()
    {
        $email = 'test@gmail.com';
        $quoteMock = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->cartRepositoryMock->expects($this->once())->method('getActive')
            ->with($this->quoteId)
            ->willReturn($quoteMock);
        $this->accountManagementMock->expects($this->once())
            ->method('isEmailAvailable')
            ->with($email, null);

        $this->model->isEmailAvailable($this->cartId, $email);
    }
}

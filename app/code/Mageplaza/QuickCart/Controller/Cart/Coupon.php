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
 * @package     Mageplaza_QuickCart
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\QuickCart\Controller\Cart;

use Exception;
use Magento\Checkout\Controller\Cart;
use Magento\Checkout\Model\Cart as CartModel;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Data\Form\FormKey\Validator;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Module\Manager;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\SalesRule\Model\CouponFactory;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Class Coupon
 * @package Mageplaza\QuickCart\Controller\Cart
 */
class Coupon extends Cart
{
    /**
     * @var CartRepositoryInterface
     */
    protected $quoteRepository;

    /**
     * @var CouponFactory
     */
    protected $couponFactory;

    /**
     * @var Manager
     */
    private $moduleManager;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param Context $context
     * @param ScopeConfigInterface $scopeConfig
     * @param Session $checkoutSession
     * @param StoreManagerInterface $storeManager
     * @param Validator $formKeyValidator
     * @param CartModel $cart
     * @param CouponFactory $couponFactory
     * @param Manager $moduleManager
     * @param CartRepositoryInterface $quoteRepository
     * @param LoggerInterface $logger
     */
    public function __construct(
        Context $context,
        ScopeConfigInterface $scopeConfig,
        Session $checkoutSession,
        StoreManagerInterface $storeManager,
        Validator $formKeyValidator,
        CartModel $cart,
        CouponFactory $couponFactory,
        Manager $moduleManager,
        CartRepositoryInterface $quoteRepository,
        LoggerInterface $logger
    ) {
        parent::__construct(
            $context,
            $scopeConfig,
            $checkoutSession,
            $storeManager,
            $formKeyValidator,
            $cart
        );
        $this->couponFactory   = $couponFactory;
        $this->quoteRepository = $quoteRepository;
        $this->moduleManager   = $moduleManager;
        $this->logger          = $logger;
    }

    /**
     * @return ResponseInterface|ResultInterface
     * @throws NoSuchEntityException
     */
    public function execute()
    {
        $resultJson    = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        $couponCode    = $this->getRequest()->getParam('remove') == 1
            ? ''
            : trim($this->getRequest()->getParam('coupon_code', ''));
        $cartQuote     = $this->cart->getQuote();
        $oldCouponCode = $cartQuote->getCouponCode() ?? '';
        $codeLength    = strlen($couponCode);

        if ($this->moduleManager->isEnabled('Mageplaza_GiftCard')) {
            $giftCardHelper  = $this->_objectManager->get('Mageplaza\GiftCard\Helper\Checkout');
            $isUsedCouponBox = $giftCardHelper->isUsedCouponBox($this->_storeManager->getStore()->getId());
            if ($isUsedCouponBox) {
                if ($couponCode == '') {
                    $giftCards = $giftCardHelper->getGiftCardsUsed($cartQuote);
                    if ($giftCards && count($giftCards)) {
                        $giftCardHelper->removeGiftCard(null, true, $cartQuote);

                        return $resultJson->setData([
                            'success' => true,
                            'message' => __('You canceled the gift card code.')
                        ]);
                    }
                } else {
                    try {
                        $giftCardHelper->addGiftCards($couponCode);

                        return $resultJson->setData([
                            'success' => true,
                            'message' => __('Your gift card was successfully applied.')
                        ]);
                    } catch (Exception $e) {
                    }
                }
            }
        }

        if (!$codeLength && !strlen($oldCouponCode)) {
            return $resultJson->setData(['success' => false, 'message' => __('Coupon code is empty.')]);
        }

        try {
            $isCodeLengthValid = $codeLength && $codeLength <= \Magento\Checkout\Helper\Cart::COUPON_CODE_MAX_LENGTH;
            $itemsCount        = $cartQuote->getItemsCount();
            if ($itemsCount) {
                $cartQuote->getShippingAddress()->setCollectShippingRates(true);
                $cartQuote->setCouponCode($isCodeLengthValid ? $couponCode : '')->collectTotals();
                $this->quoteRepository->save($cartQuote);
            }

            if ($codeLength) {
                $coupon = $this->couponFactory->create();
                $coupon->load($couponCode, 'code');
                if (!$itemsCount) {
                    if ($isCodeLengthValid && $coupon->getId()) {
                        $this->_checkoutSession->getQuote()->setCouponCode($couponCode)->save();

                        return $resultJson->setData([
                            'success' => true,
                            'message' => __('Your coupon was successfully applied.')
                        ]);
                    } else {
                        return $resultJson->setData([
                            'success' => false,
                            'message' => __('The coupon code "%1" is not valid.', $couponCode)
                        ]);
                    }
                } else {
                    if ($isCodeLengthValid && $coupon->getId() && $couponCode == $cartQuote->getCouponCode()) {
                        return $resultJson->setData([
                            'success' => true,
                            'message' => __('Your coupon was successfully applied.')
                        ]);
                    } else {
                        return $resultJson->setData([
                            'success' => false,
                            'message' => __('The coupon code "%1" is not valid.', $couponCode)
                        ]);
                    }
                }
            } else {
                return $resultJson->setData(['success' => true, 'message' => __('You canceled the coupon code.')]);
            }
        } catch (Exception $e) {
            $this->logger->critical($e);

            return $resultJson->setData(['success' => false, 'message' => $e->getMessage()]);
        }
    }
}

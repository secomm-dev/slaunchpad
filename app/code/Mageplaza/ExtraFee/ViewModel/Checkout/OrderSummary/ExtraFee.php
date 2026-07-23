<?php
namespace Mageplaza\ExtraFee\ViewModel\Checkout\OrderSummary;

use Hyva\Checkout\Model\ShippingMethodMetaData;
use Hyva\Checkout\Model\ShippingMethodMetaDataFactory;
use Magento\Checkout\Api\Data\ShippingInformationInterfaceFactory;
use Magento\Checkout\Model\Session as SessionCheckout;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Quote\Api\Data\ShippingMethodInterface;
use Mageplaza\ExtraFee\Model\Api\RuleManagement;
use Mageplaza\ExtraFee\Magewire\ClassFallback;

if (!class_exists(ShippingMethodMetaData::class)) {
    class_alias(ClassFallback::class, 'Hyva\Checkout\Model\ShippingMethodMetaData');
}
if (!class_exists(ShippingMethodMetaDataFactory::class)) {
    class_alias(ClassFallback::class, 'Hyva\Checkout\Model\ShippingMethodMetaDataFactory');
}

/**
 * Class ExtraFee
 * @package Mageplaza\ExtraFee\ViewModel\Checkout\OrderSummary
 */
class ExtraFee implements ArgumentInterface
{
    /**
     * @var SessionCheckout
     */
    protected SessionCheckout $sessionCheckout;

    /**
     * @var RuleManagement
     */
    protected $ruleManagement;

    /**
     * @var ShippingInformationInterfaceFactory
     */
    protected ShippingInformationInterfaceFactory $shippingInformationFactory;

    /**
     * @param SessionCheckout $sessionCheckout
     * @param RuleManagement $ruleManagement
     * @param ShippingInformationInterfaceFactory $shippingInformationFactory
     */
    public function __construct(
        SessionCheckout $sessionCheckout,
        RuleManagement $ruleManagement,
        ShippingInformationInterfaceFactory $shippingInformationFactory
    ) {
        $this->sessionCheckout            = $sessionCheckout;
        $this->ruleManagement             = $ruleManagement;
        $this->shippingInformationFactory = $shippingInformationFactory;
    }

    /**
     * @return \Magento\Quote\Api\Data\CartInterface|\Magento\Quote\Model\Quote
     * @throws LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getQuote()
    {
        return $this->sessionCheckout->getQuote();
    }

    /**
     * @return ShippingMethodInterface[]
     * @throws LocalizedException
     */
    public function getListRuleAreaOrderSummary()
    {
        $quote               = $this->sessionCheckout->getQuote();
        $shippingAddress     = $quote->getShippingAddress();
        $shippingInformation = $this->shippingInformationFactory->create();
        $shippingInformation->setShippingAddress($shippingAddress);

        return $this->ruleManagement->update($quote->getId(), '1,2,3', $shippingInformation);
    }
}

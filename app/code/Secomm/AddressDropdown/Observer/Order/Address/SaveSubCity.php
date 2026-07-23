<?php
/**
 * Copyright © Secomm DevTeam All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\AddressDropdown\Observer\Order\Address;

use Magento\Framework\App\State;
use Magento\Quote\Api\CartRepositoryInterface;
use Secomm\AddressDropdown\Model\CityModelFactory;
use Magento\Sales\Model\Order\Address;

class SaveSubCity implements \Magento\Framework\Event\ObserverInterface
{
    protected $quoteRepository;
    protected $addressFactory;

    public function __construct(
        \Magento\Quote\Model\Quote\AddressFactory $addressFactory,
        CartRepositoryInterface $quoteRepository,
        protected CityModelFactory $cityFactory,
        protected State $state
    ) {
        $this->quoteRepository = $quoteRepository;
        $this->addressFactory = $addressFactory;
    }

    /**
     * Execute observer
     *
     * @param \Magento\Framework\Event\Observer $observer
     * @return void
     */
    public function execute(
        \Magento\Framework\Event\Observer $observer
    ) {
        if ($this->state->getAreaCode() == 'frontend' || $this->state->getAreaCode() == 'webapi_rest') {
            $address = $observer->getEvent()->getAddress();

            $order = $address->getOrder();
            $quoteId = $order->getQuoteId();
            $quote = $this->quoteRepository->get($quoteId);
            $currentQuoteAddressShipping = $quote->getShippingAddress();
            $currentQuoteAddressBilling = $quote->getBillingAddress();

            if ($address->getAddressType() == Address::TYPE_SHIPPING) {
                $quoteAddress = $this->addressFactory->create()->load($currentQuoteAddressShipping->getId());
                if ($quoteAddress->getData('sub_city')) {
                    $address->setData('sub_city', $quoteAddress->getData('sub_city'));
                }
            }
            if ($address->getAddressType() == Address::TYPE_BILLING) {
                $quoteAddress = $this->addressFactory->create()->load($currentQuoteAddressBilling->getId());
                if ($quoteAddress->getData('sub_city')) {
                    $address->setData('sub_city', $quoteAddress->getData('sub_city'));
                }
            }
        }
    }
}

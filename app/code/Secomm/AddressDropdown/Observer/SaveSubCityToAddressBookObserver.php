<?php
/**
 * Copyright © Secomm DevTeam All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Secomm\AddressDropdown\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Magento\Customer\Api\AddressRepositoryInterface;
use Magento\Customer\Api\Data\AddressInterface;
use Secomm\AddressDropdown\Helper\Address as AddressHelper;
use Secomm\AddressDropdown\Model\Constant;

/**
 * Observes the `checkout_submit_all_after` event.
 */
class SaveSubCityToAddressBookObserver implements ObserverInterface
{
    /**
     * @var AddressRepositoryInterface
     */
    protected AddressRepositoryInterface $addressRepository;

    /**
     * @var AddressHelper
     */
    private AddressHelper $addressHelper;

    public function __construct(
        AddressRepositoryInterface $addressRepository,
        AddressHelper              $addressHelper
    )
    {
        $this->addressRepository = $addressRepository;
        $this->addressHelper = $addressHelper;
    }

    public function execute(Observer $observer)
    {
        $order = $observer->getEvent()->getOrder();
        $quote = $observer->getEvent()->getQuote();

        if (!$quote->getCustomerId()) {
            return $this; // Guest checkout, no address book to save to
        }

        if (isset($order)) {
            if ($quote->getShippingAddress() && $order->getShippingAddress()) {
                $this->saveAddressWithSubCity($quote->getShippingAddress(), $order->getShippingAddress());
            }
            if ($quote->getBillingAddress() && $order->getBillingAddress()) {
                $this->saveAddressWithSubCity($quote->getBillingAddress(), $order->getBillingAddress());
            }
        }

        if ($orders = $observer->getEvent()->getOrders()) {
            foreach ($orders as $order) {
                if ($quote->getShippingAddress() && $order->getShippingAddress()) {
                    $this->saveAddressWithSubCity($quote->getShippingAddress(), $order->getShippingAddress());
                }
                if ($quote->getBillingAddress() && $order->getBillingAddress() !== null) {
                    $this->saveAddressWithSubCity($quote->getBillingAddress(), $order->getBillingAddress());
                }
            }
        }

        return $this;
    }

    private function saveAddressWithSubCity($quoteAddress, $orderAddress)
    {
        if (!$quoteAddress || !$orderAddress) {
            return;
        }

        $customerAddressId = $quoteAddress->getCustomerAddressId();
        if (!$customerAddressId) {
            return; // Address not saved to address book
        }

        try {
            $customerAddress = $this->addressRepository->getById($customerAddressId);
            $subCity = $quoteAddress->getSubCity();

            if ($subCity) {
                $customerAddress->setCustomAttribute(Constant::SUBCITY_CODE, $subCity);
                $this->addressRepository->save($customerAddress);
            }
        } catch (\Exception $e) {
        }
    }
}

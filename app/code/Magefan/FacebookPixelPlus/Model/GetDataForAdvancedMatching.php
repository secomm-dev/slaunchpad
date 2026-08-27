<?php
/**
 * Copyright © Magefan (support@magefan.com). All rights reserved.
 * Please visit Magefan.com for license details (https://magefan.com/end-user-license-agreement).
 */

declare(strict_types=1);

namespace Magefan\FacebookPixelPlus\Model;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Api\AddressRepositoryInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Session;
use Magento\Framework\DataObject;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;

class GetDataForAdvancedMatching
{
    public const CUSTOMER_DATA = [
        'fn' => "getFirstname",
        'ln' => "getLastname",
        'em' => "getEmail",
        'db' => "getDob",
        'ge' => "getGender",
        'ph' => "getTelephone",
        'zp' => "getPostcode",
        'ct' => "getCity",
        'st' => "getRegionCode",
        'country' => "getCountryId",
        'external_id' => "getEmail"
    ];

    /**
     * @var Session
     */
    private $session;

    /**
     * @var CheckoutSession
     */
    private $checkoutSession;

    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var AddressRepositoryInterface
     */
    private $addressRepository;

    /**
     * @param Session $session
     * @param CheckoutSession $checkoutSession
     * @param OrderRepositoryInterface $orderRepository
     * @param CustomerRepositoryInterface $customerRepository
     * @param AddressRepositoryInterface $addressRepository
     */
    public function __construct(
        Session                     $session,
        CheckoutSession             $checkoutSession,
        OrderRepositoryInterface    $orderRepository,
        CustomerRepositoryInterface $customerRepository,
        AddressRepositoryInterface  $addressRepository
    ) {
        $this->session = $session;
        $this->checkoutSession = $checkoutSession;
        $this->orderRepository = $orderRepository;
        $this->customerRepository = $customerRepository;
        $this->addressRepository = $addressRepository;
    }

    /**
     * Execute advanced matching data collection
     *
     * @return array|void
     */
    public function execute()
    {
        $data = [];
        if ($this->session->isLoggedIn()) {
            $data = $this->getCustomerData();
        }
        if (empty($data)) {
            $data = $this->getOrderData() ?: $this->getQuoteData();
        }
        return $data;
    }

    /**
     * Get customer data for advanced matching
     *
     * @return array
     */
    private function getCustomerData(): array
    {
        $data = [];
        if (!$this->session->isLoggedIn()) {
            return $data;
        }

        $customer = $this->session->getCustomer();
        $address = $this->chooseAddress($customer->getDefaultBillingAddress(), $customer->getDefaultShippingAddress());
        $data = $this->getAdvancedData($customer, $address);

        return $data;
    }

    /**
     * Get order data for advanced matching
     *
     * @return array
     */
    private function getOrderData(): array
    {
        $data = [];
        $orderId = $this->checkoutSession->getLastOrderId();
        if (!$orderId) {
            return $data;
        }

        try {
            $order = $this->orderRepository->get($orderId);
        } catch (NoSuchEntityException $e) {
            return $data;
        }

        $address = $this->chooseAddress($order->getBillingAddress(), $order->getShippingAddress());
        $data = $this->getAdvancedData($order, $address);

        return $data;
    }

    /**
     * Get quote data for advanced matching
     *
     * @return array
     */
    private function getQuoteData(): array
    {
        $data = [];
        $quote = $this->checkoutSession->getQuote();
        if (!$quote->getId()) {
            return $data;
        }

        $address = $this->chooseAddress($quote->getBillingAddress(), $quote->getShippingAddress());
        $data = $this->getAdvancedData($quote, $address);

        return $data;
    }

    /**
     * Choose between billing and shipping address
     *
     * @param DataObject|null $billingAddress
     * @param DataObject|null $shippingAddress
     * @return DataObject|mixed
     */
    private function chooseAddress($billingAddress, $shippingAddress)
    {
        if ($billingAddress && $billingAddress->getFirstname()) {
            return $billingAddress;
        } elseif ($shippingAddress && $shippingAddress->getFirstname()) {
            return $shippingAddress;
        } else {
            return new DataObject();
        }
    }

    /**
     * Get advanced matching data from entity and address
     *
     * @param DataObject $entity
     * @param DataObject $address
     * @return array
     */
    private function getAdvancedData($entity, $address): array
    {
        $data = [];
        foreach (self::CUSTOMER_DATA as $parameter => $method) {
            if ($entity instanceof \Magento\Customer\Model\Data\Customer && 'getRegionCode' === $method) {
                $method = 'getRegion';
            }

            $customerMethod = str_replace('get', 'getCustomer', $method);
            $value = $entity->$customerMethod();

            if (!$value) {
                $value = $address->$method();
            }

            if (!$value) {
                $value = $entity->$method();
            }

            if (!$value) {
                continue;
            }

            $value = $this->prepareCustomerData($method, $value);
            $data[$parameter] = $value;

            if ('external_id' === $parameter && isset($data[$parameter])) {
                $data[$parameter] =  hash('sha256', $data[$parameter] ?? '');
            }

        }
        return $data;
    }

    /**
     * Prepare customer data for advanced matching
     *
     * @param string $method
     * @param mixed $value
     * @return string
     */
    private function prepareCustomerData($method, $value): string
    {
        if ('getRegion' == $method && is_object($value)) {
            $value = $value->getRegionCode() ?: '';
        } elseif ('getGender' == $method) {
            $value = ($value == 1) ? 'f' : 'm';
        } elseif (in_array($method, ['getTelephone', 'getDob'])) {
            $value = str_replace(['-', '(', ')', ' '], "", (string)$value);
        }

        $preparesString = strtolower(trim((string)$value));
        return mb_convert_encoding($preparesString, "UTF-8");
    }
}

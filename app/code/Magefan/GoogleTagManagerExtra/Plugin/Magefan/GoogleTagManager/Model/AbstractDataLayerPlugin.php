<?php
/**
 * Copyright © Magefan (support@magefan.com). All rights reserved.
 * Please visit Magefan.com for license details (https://magefan.com/end-user-license-agreement).
 */

declare(strict_types=1);

namespace Magefan\GoogleTagManagerExtra\Plugin\Magefan\GoogleTagManager\Model;

use Magefan\GoogleTagManager\Model\AbstractDataLayer;
use Magefan\GoogleTagManagerExtra\Model\Config;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\AddressFactory;
use Magento\Sales\Model\OrderFactory;
use Magento\Store\Model\StoreManagerInterface;

class AbstractDataLayerPlugin
{
    /**
     * @var Config
     */
    private $config;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var OrderFactory
     */
    private $orderFactory;

    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;

    /**
     * @var AddressFactory
     */
    private $addressFactory;

    /**
     * @var array
     */
    private $addressCache = [];

    /**
     * @param Config $config
     * @param StoreManagerInterface $storeManager
     * @param OrderFactory $orderFactory
     * @param CustomerRepositoryInterface $customerRepository
     * @param ProductRepositoryInterface $productRepository
     * @param AddressFactory $addressFactory
     */
    public function __construct(
        Config $config,
        StoreManagerInterface $storeManager,
        OrderFactory $orderFactory,
        CustomerRepositoryInterface $customerRepository,
        ProductRepositoryInterface $productRepository,
        AddressFactory $addressFactory
    ) {
        $this->config = $config;
        $this->storeManager = $storeManager;
        $this->orderFactory = $orderFactory;
        $this->customerRepository = $customerRepository;
        $this->productRepository = $productRepository;
        $this->addressFactory = $addressFactory;
    }

    /**
     * Apply custom event dimensions after the event data is wrapped.
     *
     * @param AbstractDataLayer $subject
     * @param array $result
     * @param array $data
     * @return array
     */
    public function afterEventWrap(AbstractDataLayer $subject, array $result, array $data): array
    {
        return $this->addCustomEventDimensions($subject, $result);
    }


    /**
     * Modify item event data after it has been wrapped.
     *
     * @param AbstractDataLayer $subject
     * @param array $result
     * @param array $data
     * @param mixed $product
     * @return array
     */
    public function afterItemEventWrap(AbstractDataLayer $subject, array $result, array $data, $product = null): array
    {
        if ($product) {
            $result = $this->addCustomItemDimensions($subject, $product, $result);
        }
        return $result;
    }

    /**
     * Merge configured item-level custom dimensions into item data.
     *
     * @param AbstractDataLayer $subject
     * @param mixed $product
     * @param array $data
     * @return array
     */
    private function addCustomItemDimensions(AbstractDataLayer $subject, $product, array $data): array
    {
        foreach ($this->config->getCustomItemDimensions() as $param => $attributeCode) {
            if ($value = $subject->getProductAttributeValue($product, $attributeCode)) {
                $data[$param] = $value;
            }
        }
        return $data;
    }

    /**
     * Merge configured event-level custom dimensions into event data using context entities.
     *
     * @param AbstractDataLayer $subject
     * @param array $data
     * @return array
     */
    private function addCustomEventDimensions(AbstractDataLayer $subject, array $data): array
    {
        $customDimensions = $this->config->getCustomEventDimensions();
        if (!$customDimensions) {
            return $data;
        }

        $customerId = isset($data['customer_id']) ? $data['customer_id'] : null;
        $productId  = isset($data['product_id']) ? $data['product_id'] : null;
        $orderIncrementId = isset($data['ecommerce']['transaction_id']) ? $data['ecommerce']['transaction_id'] : null;

        $customer = null;
        if ($customerId) {
            try {
                $customer = $this->customerRepository->getById($customerId);
            } catch (\Exception $e) { // phpcs:ignore
                /* Do nothing */
            }
        }

        $product = null;
        if ($productId) {
            try {
                $product = $this->productRepository->getById($productId);
            } catch (\Exception $e) { // phpcs:ignore
                /* Do nothing */
            }
        }

        $order = null;
        if ($orderIncrementId) {
            try {
                $order = $this->orderFactory->create()->loadByIncrementId($orderIncrementId);
                if (!$order->getId()) {
                    $order = null;
                }
            } catch (\Exception $e) { // phpcs:ignore
                /* Do nothing */
            }
        }

        foreach ($customDimensions as $param => $valueSource) {
            if (strpos($valueSource, '.') === false) {
                continue;
            }
            [$entity, $attribute] = explode('.', $valueSource, 2);

            $value = null;
            if ($entity === 'product' && $product) {
                $value = $subject->getProductAttributeValue($product, $attribute) ?: null;
            } elseif ($entity === 'customer' && $customer) {
                $value = $this->getCustomerAttributeValue($customer, $attribute);
            } elseif ($entity === 'address') {
                $value = $this->getAddressAttributeValue($customer, $order, $attribute);
            } elseif ($entity === 'order' && $order) {
                $value = (string)$order->getData($attribute);
            } elseif ($entity === 'store') {
                try {
                    $store = $this->storeManager->getStore();

                    if ($attribute === 'currency') {
                        $value = $store->getCurrentCurrencyCode();
                    } else {
                        $value = (string)$store->getData($attribute);
                    }
                } catch (\Exception $e) { // phpcs:ignore
                    /* Do nothing */
                }
            }

            if ($value) {
                $data[$param] = $value;
            }
        }

        return $data;
    }

    /**
     * Return customer attribute value, supporting both typed getters and custom attributes.
     *
     * @param mixed $customer
     * @param string $attribute
     * @return string|null
     */
    private function getCustomerAttributeValue($customer, string $attribute): ?string
    {
        $getter = 'get' . str_replace('_', '', ucwords($attribute, '_'));
        if (method_exists($customer, $getter)) {
            $raw = $customer->$getter();
        } else {
            $attr = $customer->getCustomAttribute($attribute);
            $raw  = $attr ? $attr->getValue() : null;
        }
        return $raw !== null && $raw !== '' ? (string)$raw : null;
    }

    /**
     * Return billing address attribute value from order or customer context.
     *
     * @param mixed $customer
     * @param mixed $order
     * @param string $attribute
     * @return string|null
     */
    private function getAddressAttributeValue($customer, $order, string $attribute): ?string
    {
        if ($order) {
            $address = $order->getBillingAddress();
            if ($address) {
                return (string)$address->getData($attribute);
            }
        } elseif ($customer && $customer->getId()) {
            $billingId = $customer->getDefaultBilling();
            if ($billingId) {
                if (!isset($this->addressCache[$billingId])) {
                    $this->addressCache[$billingId] = $this->addressFactory->create()->load($billingId);
                }
                $address = $this->addressCache[$billingId];
                if ($address->getId()) {
                    return (string)$address->getData($attribute);
                }
            }
        }
        return null;
    }
}
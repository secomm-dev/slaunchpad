<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Pancake\Model\Order;

use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderItemInterface;
use Magento\Sales\Model\Order as SalesOrder;
use Secomm\Pancake\Model\Config\PancakeConfig;

/**
 * Map Magento order to POS create-order JSON.
 * Q4: custom_id = increment_id. PNC-004: warehouse_id required from core map.
 *
 * @param OrderInterface $order Magento sales order
 * @param string $warehouseId Pancake warehouse UUID from core WarehouseMapResolver
 * @return array<string, mixed> POS payload (no api_key)
 */
class PayloadBuilder
{
    public function __construct(
        private readonly PancakeConfig $config
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function build(OrderInterface $order, string $warehouseId): array
    {
        $shipping = null;
        if ($order instanceof SalesOrder) {
            $shipping = $order->getShippingAddress();
        }
        $billing = $order->getBillingAddress();
        $address = $shipping ?: $billing;

        $fullName = trim(($address ? (string) $address->getFirstname() : '') . ' ' . ($address ? (string) $address->getLastname() : ''));
        $phone = $address ? (string) $address->getTelephone() : '';

        $street = '';
        if ($address) {
            $streetParts = $address->getStreet();
            $street = is_array($streetParts) ? implode(', ', $streetParts) : (string) $streetParts;
        }

        $region = $address ? (string) $address->getRegion() : '';
        $city = $address ? (string) $address->getCity() : '';
        $postcode = $address ? (string) $address->getPostcode() : '';
        $country = $address ? (string) $address->getCountryId() : '';
        $fullAddress = trim(implode(', ', array_filter([$street, $city, $region, $postcode, $country])));

        $items = [];
        foreach ($order->getItems() ?? [] as $item) {
            if (!$item instanceof OrderItemInterface) {
                continue;
            }
            if ($item->getParentItemId()) {
                continue;
            }
            $qty = (float) $item->getQtyOrdered();
            $price = (float) $item->getPrice();
            $weight = (float) $item->getWeight() * 1000;
            $items[] = [
                'quantity' => $qty > 0 ? $qty : 1,
                'one_time_product' => true,
                'variation_info' => [
                    'name' => (string) $item->getName(),
                    'retail_price' => (int) round($price),
                    'weight' => (int) max(0, round($weight)),
                ],
            ];
        }

        $storeId = $order->getStoreId() !== null ? (int) $order->getStoreId() : null;
        $incrementId = (string) $order->getIncrementId();

        return [
            'shop_id' => (int) $this->config->getShopId($storeId),
            'warehouse_id' => $warehouseId,
            'bill_full_name' => $fullName !== '' ? $fullName : 'Customer',
            'bill_phone_number' => $phone !== '' ? $phone : '0000000000',
            'custom_id' => $incrementId,
            'note' => $incrementId,
            'shipping_fee' => (int) round((float) $order->getShippingAmount()),
            'shipping_address' => [
                'address' => $street !== '' ? $street : $fullAddress,
                'full_address' => $fullAddress,
                'full_name' => $fullName !== '' ? $fullName : 'Customer',
                'phone_number' => $phone !== '' ? $phone : '0000000000',
                'country_code' => $country !== '' ? $country : null,
                'post_code' => $postcode !== '' ? $postcode : null,
            ],
            'items' => $items,
        ];
    }
}

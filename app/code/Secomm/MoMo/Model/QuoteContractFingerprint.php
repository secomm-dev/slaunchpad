<?php
/**
 * Immutable payment-contract fingerprint of a MoMo quote.
 *
 * @author    Secomm Teams
 * @copyright Copyright (c) 2026 Secomm (https://www.secomm.vn)
 * @package   Secomm_MoMo
 */
declare(strict_types=1);

namespace Secomm\MoMo\Model;

use Magento\Quote\Model\Quote;

/**
 * Sha-256 fingerprint over the payment-relevant contract of a quote:
 * quote id, reserved order id, store, currency, VND amount, sorted items
 * (sku|product_id|qty, bundle children via child SKU), shipping method,
 * payment method, coupon, applied rule ids and hashed normalized
 * addresses. Locked on the attempt at initiation; the finalizer refuses to
 * place an order when the CURRENT quote no longer matches.
 */
class QuoteContractFingerprint
{
    /**
     * Calculate the fingerprint for a quote.
     *
     * @param Quote $quote
     * @param int $vndAmount VND snapshot included in the fingerprint.
     * @return string
     */
    public function calculate(Quote $quote, int $vndAmount): string
    {
        $items = [];
        foreach ($quote->getAllItems() as $item) {
            $children = $item->getChildren() ?? [];
            $childSkus = [];
            foreach ($children as $child) {
                $childSkus[] = (string)$child->getSku();
            }
            sort($childSkus);
            $items[] = implode('|', [
                (string)$item->getSku(),
                (string)$item->getProductId(),
                (string)$item->getQty(),
                implode(',', $childSkus),
            ]);
        }
        sort($items, SORT_STRING);

        $parts = [
            'quote' => (string)$quote->getId(),
            'reserved' => (string)$quote->getReservedOrderId(),
            'store' => (string)$quote->getStoreId(),
            'currency' => (string)$quote->getQuoteCurrencyCode(),
            'amount_vnd' => (string)$vndAmount,
            'items' => $items,
            'shipping' => (string)$quote->getShippingAddress()->getShippingMethod(),
            'payment' => (string)($quote->getPayment()->getMethod() ?? ''),
            'coupon' => (string)$quote->getCouponCode(),
            'rules' => $this->ruleIds($quote),
            'addresses' => $this->addressHashes($quote),
        ];

        return hash('sha256', json_encode($parts) ?: '');
    }

    /**
     * Whether a stored fingerprint matches a freshly calculated one.
     *
     * @param string|null $stored
     * @param string $current
     * @return bool
     */
    public function matches(?string $stored, string $current): bool
    {
        return $stored !== null && $stored !== '' && hash_equals($current, $stored);
    }

    /**
     * Sorted applied rule ids of the quote.
     *
     * @param Quote $quote
     * @return list<string>
     */
    private function ruleIds(Quote $quote): array
    {
        $ids = [];
        foreach ([$quote->getAppliedRuleIds(), $quote->getShippingAddress()->getAppliedRuleIds()] as $raw) {
            foreach (explode(',', (string)$raw) as $id) {
                if ($id !== '') {
                    $ids[] = $id;
                }
            }
        }
        $ids = array_values(array_unique($ids));
        sort($ids, SORT_NUMERIC);

        return $ids;
    }

    /**
     * Hashes of the normalized billing + shipping addresses.
     *
     * @param Quote $quote
     * @return list<string>
     */
    private function addressHashes(Quote $quote): array
    {
        $hashes = [];
        foreach ([$quote->getBillingAddress(), $quote->getShippingAddress()] as $address) {
            if ($address === null || !$address->getId() && $address->getData() === []) {
                $hashes[] = 'none';
                continue;
            }
            $hashes[] = hash(
                'sha256',
                implode('|', [
                    strtolower(trim((string)$address->getEmail())),
                    strtolower(trim((string)$address->getFirstname())),
                    strtolower(trim((string)$address->getLastname())),
                    trim((string)$address->getTelephone()),
                    implode(',', (array)($address->getStreet() ?: [])),
                    strtolower(trim((string)$address->getCity())),
                    strtolower(trim((string)$address->getPostcode())),
                    strtolower(trim((string)$address->getCountryId())),
                    strtolower(trim((string)$address->getRegion())),
                ])
            );
        }

        return $hashes;
    }
}

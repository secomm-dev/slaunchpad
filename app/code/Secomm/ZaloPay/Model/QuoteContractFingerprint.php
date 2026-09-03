<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\ZaloPay\Model;

use Magento\Quote\Model\Quote;

/**
 * Deterministic fingerprint of the QUOTE PAYMENT CONTRACT a ZaloPay attempt
 * was created against (BLOCKER 1 fix).
 *
 * The provider is paid for one specific contract; Magento may only
 * auto-create the order for that same contract. The amount snapshot alone
 * cannot prove that — a qty change, item swap, shipping-method or address
 * edit can land on the same total. This class normalizes the
 * contract-defining quote state into one sha-256 hash:
 *
 *   quote_id, reserved_order_id, store_id, quote currency, grand total,
 *   base grand total, provider VND amount,
 *   per item (incl. children, sorted by SKU): sku, product_id, qty —
 *   configurable/bundle selections are captured through the child SKUs,
 *   shipping method, coupon code, applied sales-rule ids,
 *   sha-256 of the normalized shipping + billing address fields.
 *
 * Addresses are hashed (never stored raw): the fingerprint needs equality,
 * not content — no additional PII is persisted. No secret key material is
 * involved: the hash is a checksum for comparison, not a signature.
 */
class QuoteContractFingerprint
{
    /**
     * Address fields included in the address hash, in fixed order. Values
     * are trimmed and lowercased so cosmetic edits do not break contracts.
     */
    private const ADDRESS_FIELDS = [
        'firstname',
        'lastname',
        'company',
        'street',
        'city',
        'region',
        'postcode',
        'country_id',
        'telephone',
        'vat_id',
    ];

    /**
     * Compute the fingerprint of the quote's payment contract.
     *
     * @param Quote $quote Quote with totals collected and reserved order id set.
     * @param int $amountVnd Provider VND amount locked for this attempt.
     * @return string 64-char hex sha-256.
     */
    public function calculate(Quote $quote, int $amountVnd): string
    {
        $contract = [
            'quote_id' => (int)$quote->getId(),
            'reserved_order_id' => (string)$quote->getReservedOrderId(),
            'store_id' => (int)$quote->getStoreId(),
            'currency' => (string)$quote->getQuoteCurrencyCode(),
            'grand_total' => $this->formatAmount((float)$quote->getGrandTotal()),
            'base_grand_total' => $this->formatAmount((float)$quote->getBaseGrandTotal()),
            'amount_vnd' => $amountVnd,
            'items' => $this->itemSignatures($quote),
            'shipping_method' => $this->normalize((string)$quote->getShippingAddress()?->getShippingMethod()),
            'coupon_code' => $this->normalize((string)$quote->getCouponCode()),
            'applied_rule_ids' => $this->normalize((string)$quote->getAppliedRuleIds()),
            'shipping_address' => $this->addressHash($quote->getShippingAddress()),
            'billing_address' => $this->addressHash($quote->getBillingAddress()),
        ];

        // Canonical key order makes json_encode output independent of build
        // order. Done manually (recursive ksort): JSON_SORT_KEYS is missing
        // from this project's PHP runtime (verified 2026-08).
        return hash('sha256', (string)json_encode($this->canonicalize($contract)));
    }

    /**
     * Whether two fingerprints match.
     *
     * Null-safe constant-time compare (legacy rows without a hash never
     * compare equal).
     *
     * @param string|null $persisted
     * @param string $current
     * @return bool
     */
    public function matches(?string $persisted, string $current): bool
    {
        return $persisted !== null
            && $persisted !== ''
            && hash_equals($persisted, $current);
    }

    /**
     * Build the per-item signatures for the contract.
     *
     * ALL items (children included — the child SKU carries the
     * configurable/bundle selection), sorted so cart display order is
     * irrelevant.
     *
     * @param Quote $quote
     * @return array
     */
    private function itemSignatures(Quote $quote): array
    {
        $signatures = [];
        foreach ($quote->getAllItems() as $item) {
            $signatures[] = implode('|', [
                $this->normalize((string)$item->getSku()),
                (int)$item->getProductId(),
                $this->formatAmount((float)$item->getQty()),
            ]);
        }
        sort($signatures, SORT_STRING);

        return $signatures;
    }

    /**
     * Hash one address (null when the address is absent).
     *
     * @param \Magento\Quote\Api\Data\AddressInterface|null $address
     * @return string|null sha-256 of the normalized address, null when absent.
     */
    private function addressHash(?\Magento\Quote\Api\Data\AddressInterface $address): ?string
    {
        if ($address === null || !$address->getId() && $address->getCountryId() === null) {
            return null;
        }
        $parts = [];
        foreach (self::ADDRESS_FIELDS as $field) {
            $value = $address->getData($field);
            $parts[$field] = is_array($value)
                ? array_map([$this, 'normalize'], $value)
                : $this->normalize((string)$value);
        }

        return hash('sha256', (string)json_encode($this->canonicalize($parts)));
    }

    /**
     * Recursively sort array keys so encoding order never depends on build order.
     *
     * @param mixed $value
     * @return mixed
     */
    private function canonicalize($value)
    {
        if (is_array($value)) {
            ksort($value, SORT_STRING);
            foreach ($value as $key => $child) {
                $value[$key] = $this->canonicalize($child);
            }
        }

        return $value;
    }

    /**
     * Deterministic numeric formatting (avoids 100 vs 100.0 drift).
     *
     * @param float $value
     * @return string
     */
    private function formatAmount(float $value): string
    {
        return rtrim(rtrim(sprintf('%.4F', $value), '0'), '.');
    }

    /**
     * Normalize a scalar contract value (trim + lowercase).
     *
     * @param string $value
     * @return string
     */
    private function normalize(string $value): string
    {
        return strtolower(trim($value));
    }
}

<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Model;

use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\Model\AbstractModel;
use Secomm\ShippingCore\Model\ResourceModel\Zone as ZoneResource;

/**
 * FEAT-QA23PZ / DEC-FEATQA23PZ-001 — persistent merchant-managed canonical shipping zone.
 *
 * Storage shape for `secomm_shipping_zone`. The runtime domain NEVER consumes this model:
 * eligibility evaluates immutable `CanonicalZoneInterface` VOs loaded through the zone
 * repository / persistent registry. Code lists are canonical VN_ADMIN_2025 codes only —
 * provider-specific identities are forbidden by the domain contract.
 */
class Zone extends AbstractModel implements IdentityInterface
{
    /** Cache tag — one shared tag keeps invalidation trivial for a low-volume config table. */
    public const CACHE_TAG = 'secomm_shipping_zone';

    protected $_cacheTag = self::CACHE_TAG;

    protected $_eventPrefix = 'secomm_shipping_zone';

    protected $_eventObject = 'zone';

    /**
     * @return void
     */
    protected function _construct()
    {
        $this->_init(ZoneResource::class);
    }

    public function getIdentities(): array
    {
        return [self::CACHE_TAG . '_' . $this->getId()];
    }

    public function getCode(): string
    {
        return (string) $this->getData('code');
    }

    public function setCode(string $code): self
    {
        return $this->setData('code', $code);
    }

    public function getLabel(): string
    {
        return (string) $this->getData('label');
    }

    public function setLabel(string $label): self
    {
        return $this->setData('label', $label);
    }

    public function isEnabled(): bool
    {
        return (bool) $this->getData('enabled');
    }

    public function setEnabled(bool $enabled): self
    {
        return $this->setData('enabled', $enabled ? 1 : 0);
    }

    /**
     * @return string[] canonical province codes (VN-XX)
     */
    public function getIncludeProvinceCodes(): array
    {
        return $this->decodeJson('include_province_codes');
    }

    public function setIncludeProvinceCodes(array $codes): self
    {
        return $this->setData('include_province_codes', $codes);
    }

    /**
     * @return string[] canonical ward codes (VNA25-*)
     */
    public function getIncludeWardCodes(): array
    {
        return $this->decodeJson('include_ward_codes');
    }

    public function setIncludeWardCodes(array $codes): self
    {
        return $this->setData('include_ward_codes', $codes);
    }

    /**
     * @return string[] canonical ward codes (VNA25-*)
     */
    public function getExcludeWardCodes(): array
    {
        return $this->decodeJson('exclude_ward_codes');
    }

    public function setExcludeWardCodes(array $codes): self
    {
        return $this->setData('exclude_ward_codes', $codes);
    }

    /**
     * Code-list columns are JSON arrays; the resource serializes on write.
     *
     * @return string[]
     */
    private function decodeJson(string $field): array
    {
        $raw = $this->getData($field);
        if (is_array($raw)) {
            return array_values(array_map('strval', $raw));
        }
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        return is_array($decoded) ? array_values(array_map('strval', $decoded)) : [];
    }
}

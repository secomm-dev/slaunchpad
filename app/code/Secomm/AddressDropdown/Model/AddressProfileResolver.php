<?php
declare(strict_types=1);
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\AddressDropdown\Model;

use Magento\Framework\Serialize\Serializer\Serialize;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use Secomm\AddressDropdown\Api\AddressProfileResolverInterface;
use Secomm\AddressDropdown\Api\Data\AddressProfileInterface;
use Secomm\AddressDropdown\Api\NoSuchProfileException;
use Secomm\AddressDropdown\Model\Profile\ProfilePool;

/**
 * FEAT-2PZQKJ / TASK-NW66H9 — config-driven profile resolution (DEC-FEAT2PZQKJ-001 Decision 3):
 * store-scoped `address/profiles/mapping` (countryId => profile code). No rules engine.
 *
 * Defensive by design on the storefront path: unmapped country => null (native Magento fields);
 * mapped-but-undeclared profile code => warning log + null, never an exception.
 */
class AddressProfileResolver implements AddressProfileResolverInterface
{
    public const XML_PATH_PROFILE_MAPPING = 'address/profiles/mapping';

    public function __construct(
        private readonly ProfilePool $profilePool,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly StoreManagerInterface $storeManager,
        private readonly Serialize $serializer,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @inheritDoc
     */
    public function resolve(string $countryId, ?string $context = null): ?AddressProfileInterface
    {
        $profileCode = $this->getMappedProfileCode($countryId);
        if ($profileCode === null || $profileCode === '') {
            return null; // native Magento behavior
        }

        try {
            return $this->profilePool->getProfile($profileCode);
        } catch (NoSuchProfileException $exception) {
            // Store config references a profile no module declares — config fault, not a runtime fault.
            $this->logger->warning(
                'Secomm_AddressDropdown: country is mapped to an undeclared address profile; '
                . 'falling back to native Magento address fields.',
                ['country_id' => $countryId, 'profile_code' => $profileCode]
            );

            return null;
        }
    }

    /**
     * Store-scoped countryId => profile code from the serialized mapping config.
     */
    private function getMappedProfileCode(string $countryId): ?string
    {
        $raw = $this->scopeConfig->getValue(
            self::XML_PATH_PROFILE_MAPPING,
            ScopeInterface::SCOPE_STORE,
            $this->storeManager->getStore()->getId()
        );

        if ($raw === null || $raw === '') {
            return null;
        }

        $mapping = is_array($raw) ? $raw : $this->unserializeSafely((string)$raw);
        if (!is_array($mapping)) {
            return null;
        }

        $profileCode = $mapping[$countryId] ?? null;

        return $profileCode === null ? null : (string)$profileCode;
    }

    /**
     * A corrupted stored value must degrade to native fallback, never break the storefront.
     */
    private function unserializeSafely(string $raw): mixed
    {
        try {
            return $this->serializer->unserialize($raw);
        } catch (\InvalidArgumentException $exception) {
            $this->logger->warning(
                'Secomm_AddressDropdown: address profile mapping config is corrupted; using native fallback.',
                ['exception' => $exception->getMessage()]
            );

            return null;
        }
    }
}

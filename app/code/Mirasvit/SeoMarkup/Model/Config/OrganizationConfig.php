<?php
/**
 * Mirasvit
 *
 * This source file is subject to the Mirasvit Software License, which is available at https://mirasvit.com/license/.
 * Do not edit or add to this file if you wish to upgrade the to newer versions in the future.
 * If you wish to customize this module for your needs.
 * Please refer to http://www.magentocommerce.com for more information.
 *
 * @category  Mirasvit
 * @package   mirasvit/module-seo
 * @version   2.12.8
 * @copyright Copyright (C) 2026 Mirasvit (https://mirasvit.com/)
 */


declare(strict_types=1);

namespace Mirasvit\SeoMarkup\Model\Config;

use Magento\Store\Model\ScopeInterface;
use Mirasvit\SeoMarkup\Model\Config;

class OrganizationConfig extends Config
{
    const DISPLAY_SCOPE_HOME     = 0;
    const DISPLAY_SCOPE_ALL      = 1;
    const DISPLAY_SCOPE_SPECIFIC = 2;

    private $socialLinkConfigs
        = [
            'seo_markup/organization/youtube_link',
            'seo_markup/organization/facebook_link',
            'seo_markup/organization/linkedin_link',
            'seo_markup/organization/instagram_link',
            'seo_markup/organization/pinterest_link',
            'seo_markup/organization/tumblr_link',
            'seo_markup/organization/twitter_link',
        ];

    private $contactPointTypes
        = [
            'customer service'  => [
                'prefix'            => 'contact_customer_service',
                'fallbackEmailPath' => 'trans_email/ident_support/email',
            ],
            'sales'             => [
                'prefix'            => 'contact_sales',
                'fallbackEmailPath' => 'trans_email/ident_sales/email',
            ],
            'technical support' => [
                'prefix'            => 'contact_technical_support',
                'fallbackEmailPath' => null,
            ],
        ];

    public function isRsEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            'seo_markup/organization/is_rs_enabled',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function isCustomName(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            'seo_markup/organization/is_custom_name',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getCustomName(?int $storeId = null): string
    {
        return trim((string)$this->scopeConfig->getValue(
            'seo_markup/organization/custom_name',
            ScopeInterface::SCOPE_STORE,
            $storeId
        ));
    }


    public function isCustomAddressCountry(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            'seo_markup/organization/is_custom_address_country',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getCustomAddressCountry(?int $storeId = null): string
    {
        return trim((string)$this->scopeConfig->getValue(
            'seo_markup/organization/custom_address_country',
            ScopeInterface::SCOPE_STORE,
            $storeId
        ));
    }

    public function isCustomAddressLocality(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            'seo_markup/organization/is_custom_address_locality',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getCustomAddressLocality(?int $storeId = null): string
    {
        return trim((string)$this->scopeConfig->getValue(
            'seo_markup/organization/address_locality',
            ScopeInterface::SCOPE_STORE,
            $storeId
        ));
    }

    public function isCustomAddressRegion(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            'seo_markup/organization/is_custom_address_region',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getCustomAddressRegion(?int $storeId = null): string
    {
        return trim((string)$this->scopeConfig->getValue(
            'seo_markup/organization/custom_address_region',
            ScopeInterface::SCOPE_STORE,
            $storeId
        ));
    }

    public function isCustomPostalCode(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            'seo_markup/organization/is_custom_postal_code',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getCustomPostalCode(?int $storeId = null): string
    {
        return trim((string)$this->scopeConfig->getValue(
            'seo_markup/organization/custom_postal_code',
            ScopeInterface::SCOPE_STORE,
            $storeId
        ));
    }

    public function isCustomStreetAddress(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            'seo_markup/organization/is_custom_street_address',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getCustomStreetAddress(?int $storeId = null): string
    {
        return trim((string)$this->scopeConfig->getValue(
            'seo_markup/organization/custom_street_address',
            ScopeInterface::SCOPE_STORE,
            $storeId
        ));
    }

    public function isCustomTelephone(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            'seo_markup/organization/is_custom_telephone',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getCustomTelephone(?int $storeId = null): string
    {
        return trim((string)$this->scopeConfig->getValue(
            'seo_markup/organization/custom_telephone',
            ScopeInterface::SCOPE_STORE,
            $storeId
        ));
    }

    public function getCustomFaxNumber(?int $storeId = null): string
    {
        return trim((string)$this->scopeConfig->getValue(
            'seo_markup/organization/custom_fax_number',
            ScopeInterface::SCOPE_STORE,
            $storeId
        ));
    }

    public function getLegalName(?int $storeId = null): string
    {
        return trim((string)$this->scopeConfig->getValue(
            'seo_markup/organization/legal_name',
            ScopeInterface::SCOPE_STORE,
            $storeId
        ));
    }

    public function getFoundingDate(?int $storeId = null): string
    {
        return trim((string)$this->scopeConfig->getValue(
            'seo_markup/organization/founding_date',
            ScopeInterface::SCOPE_STORE,
            $storeId
        ));
    }

    public function getNumberOfEmployees(?int $storeId = null): string
    {
        return trim((string)$this->scopeConfig->getValue(
            'seo_markup/organization/number_of_employees',
            ScopeInterface::SCOPE_STORE,
            $storeId
        ));
    }

    public function getAreaServed(?int $storeId = null): string
    {
        return trim((string)$this->scopeConfig->getValue(
            'seo_markup/organization/area_served',
            ScopeInterface::SCOPE_STORE,
            $storeId
        ));
    }

    public function isCustomEmail(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            'seo_markup/organization/is_custom_email',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getCustomEmail(?int $storeId = null): ?string
    {
        return $this->scopeConfig->getValue(
            'seo_markup/organization/custom_email',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getSocialLinks(?int $storeId = null): array
    {
        $socialLinks = [];
        foreach ($this->socialLinkConfigs as $socialLinkConfig) {
            $socialLink = $this->scopeConfig->getValue(
                $socialLinkConfig,
                ScopeInterface::SCOPE_STORE,
                $storeId
            );

            if (isset($socialLink)) {
                $socialLinks[] = $socialLink;
            }
        }

        return $socialLinks;
    }

    public function getContactPoints(?int $storeId = null): array
    {
        $contactPoints = [];

        foreach ($this->contactPointTypes as $contactType => $config) {
            $configPrefix = $config['prefix'];

            $telephone = trim((string)$this->scopeConfig->getValue(
                'seo_markup/organization/' . $configPrefix . '_telephone',
                ScopeInterface::SCOPE_STORE,
                $storeId
            ));
            $email      = $this->getContactEmail($configPrefix, $config['fallbackEmailPath'], $storeId);
            $areaServed = trim((string)$this->scopeConfig->getValue(
                'seo_markup/organization/' . $configPrefix . '_area_served',
                ScopeInterface::SCOPE_STORE,
                $storeId
            ));

            if (!$telephone && !$email) {
                continue;
            }

            $contactPoint = [
                '@type'       => 'ContactPoint',
                'contactType' => $contactType,
            ];

            if ($telephone) {
                $contactPoint['telephone'] = $telephone;
            }

            if ($email) {
                $contactPoint['email'] = $email;
            }

            if ($areaServed) {
                $contactPoint['areaServed'] = $areaServed;
            }

            $contactPoints[] = $contactPoint;
        }

        return $contactPoints;
    }

    private function getContactEmail(string $configPrefix, ?string $fallbackEmailPath, ?int $storeId): string
    {
        if ($fallbackEmailPath === null) {
            return trim((string)$this->scopeConfig->getValue(
                'seo_markup/organization/' . $configPrefix . '_email',
                ScopeInterface::SCOPE_STORE,
                $storeId
            ));
        }

        $isCustom = $this->scopeConfig->isSetFlag(
            'seo_markup/organization/is_custom_' . $configPrefix . '_email',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        if ($isCustom) {
            return trim((string)$this->scopeConfig->getValue(
                'seo_markup/organization/' . $configPrefix . '_email',
                ScopeInterface::SCOPE_STORE,
                $storeId
            ));
        }

        return trim((string)$this->scopeConfig->getValue($fallbackEmailPath, ScopeInterface::SCOPE_STORE, $storeId));
    }

    public function getDisplayScope(?int $storeId = null): int
    {
        return (int)$this->scopeConfig->getValue(
            'seo_markup/organization/display_scope',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getCmsPages(?int $storeId = null): array
    {
        $value = (string)$this->scopeConfig->getValue(
            'seo_markup/organization/cms_pages',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return explode(',', $value);
    }
}

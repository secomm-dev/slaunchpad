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

namespace Mirasvit\SeoMarkup\Block\Rs;

use Magento\Directory\Model\RegionFactory;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Locale\ListsInterface as LocaleListsInterface;
use Magento\Framework\View\Element\Template;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\View\Element\Template\Context;
use Magento\Store\Model\Information as StoreInformation;
use Magento\Store\Model\Store;
use Magento\Theme\Block\Html\Header\Logo;
use Mirasvit\SeoMarkup\Model\Config;
use Mirasvit\SeoMarkup\Model\Config\AeoConfig;
use Mirasvit\SeoMarkup\Model\Config\OrganizationConfig;

class Organization extends Template
{
    private $store;

    private $organizationConfig;

    private $aeoConfig;

    private $context;

    private $localeLists;

    private $regionFactory;

    private $logo;

    private $storeId;

    private $serializer;

    public function __construct(
        OrganizationConfig   $organizationConfig,
        AeoConfig            $aeoConfig,
        LocaleListsInterface $localeLists,
        RegionFactory        $regionFactory,
        Logo                 $logo,
        Context              $context,
        Json                 $serializer
    ) {
        $this->organizationConfig = $organizationConfig;
        $this->aeoConfig          = $aeoConfig;
        $this->localeLists        = $localeLists;
        $this->regionFactory      = $regionFactory;
        $this->logo               = $logo;
        $this->context            = $context;
        $this->serializer         = $serializer;

        $this->store = $context->getStoreManager()->getStore();

        parent::__construct($context);
    }

    /**
     * {@inheritdoc}
     */
    protected function _toHtml()
    {
        if (!$this->canShow()) {
            return '';
        }

        $data = $this->getJsonData();

        return '<script type="application/ld+json">' . $this->serializer->serialize($data) . '</script>';
    }

    private function getJsonData(): array
    {
        $data = [
            "@context" => Config::HTTP_SCHEMA_ORG,
            "@type"    => "Organization",
        ];

        if ($this->aeoConfig->isAeoEnabled($this->getStoreId())) {
            $data['@id'] = $this->getBaseUrl() . AeoConfig::ID_ORGANIZATION;
        }

        $values = [
            'url'          => $this->getBaseUrl(),
            'logo'         => $this->getLogoUrl(),
            'name'         => $this->getName(),
            'legalName'    => $this->getLegalName(),
            'telephone'    => $this->getTelephone(),
            'faxNumber'    => $this->getFaxNumber(),
            'email'        => $this->getEmail(),
            'foundingDate' => $this->getFoundingDate(),
            'areaServed'   => $this->getAreaServed(),
        ];

        foreach ($values as $key => $value) {
            $value = trim($value);

            if ($value) {
                $data[$key] = $value;
            }
        }

        $values = [
            'addressCountry'  => $this->getAddressCountry(),
            'addressLocality' => $this->getAddressLocality(),
            'postalCode'      => $this->getPostalCode(),
            'streetAddress'   => $this->getStreetAddress(),
            'addressRegion'   => $this->getAddressRegion(),
        ];
        $address = [];

        foreach ($values as $key => $value) {
            $value = trim($value);
            if ($value) {
                $address[$key] = $value;
            }
        }

        if (count($address)) {
            $data['address'] = array_merge([
                '@type' => 'PostalAddress',
            ], $address);
        }

        if ($socialLinks = $this->getSocialLinks()) {
            $data['sameAs'] = $socialLinks;
        }

        if ($contactPoints = $this->getContactPoints()) {
            $data['contactPoint'] = $contactPoints;
        }

        $numberOfEmployees = $this->getNumberOfEmployees();

        if ($numberOfEmployees) {
            $data['numberOfEmployees'] = [
                '@type' => 'QuantitativeValue',
                'value' => (int)$numberOfEmployees,
            ];
        }

        return $data;
    }

    private function getName(): string
    {
        if ($this->organizationConfig->isCustomName($this->getStoreId())) {
            return $this->organizationConfig->getCustomName($this->getStoreId());
        }

        return (string)$this->store->getConfig(StoreInformation::XML_PATH_STORE_INFO_NAME);
    }

    private function getTelephone(): string
    {
        if ($this->organizationConfig->isCustomTelephone($this->getStoreId())) {
            return $this->organizationConfig->getCustomTelephone($this->getStoreId());
        }

        return (string)$this->store->getConfig(StoreInformation::XML_PATH_STORE_INFO_PHONE);
    }

    private function getFaxNumber(): string
    {
        return $this->organizationConfig->getCustomFaxNumber($this->getStoreId());
    }

    private function getLegalName(): string
    {
        return $this->organizationConfig->getLegalName($this->getStoreId());
    }

    private function getFoundingDate(): string
    {
        return $this->organizationConfig->getFoundingDate($this->getStoreId());
    }

    private function getNumberOfEmployees(): string
    {
        return $this->organizationConfig->getNumberOfEmployees($this->getStoreId());
    }

    private function getAreaServed(): string
    {
        return $this->organizationConfig->getAreaServed($this->getStoreId());
    }

    private function getEmail(): string
    {
        if ($this->organizationConfig->isCustomEmail($this->getStoreId())) {
            return (string)$this->organizationConfig->getCustomEmail($this->getStoreId());
        }

        return (string)$this->context->getScopeConfig()->getValue('trans_email/ident_general/email');
    }

    public function getAddressCountry(): string
    {
        if ($this->organizationConfig->isCustomAddressCountry($this->getStoreId())) {
            return $this->organizationConfig->getCustomAddressCountry($this->getStoreId());
        }

        return (string)$this->localeLists->getCountryTranslation(
            $this->store->getConfig(StoreInformation::XML_PATH_STORE_INFO_COUNTRY_CODE)
        );
    }

    public function getAddressLocality(): string
    {
        if ($this->organizationConfig->isCustomAddressLocality($this->getStoreId())) {
            return $this->organizationConfig->getCustomAddressLocality($this->getStoreId());
        }

        return (string)$this->store->getConfig(StoreInformation::XML_PATH_STORE_INFO_CITY);
    }

    public function getPostalCode(): string
    {
        if ($this->organizationConfig->isCustomPostalCode($this->getStoreId())) {
            return $this->organizationConfig->getCustomPostalCode($this->getStoreId());
        }

        return (string)$this->store->getConfig(StoreInformation::XML_PATH_STORE_INFO_POSTCODE);
    }

    public function getStreetAddress(): string
    {
        if ($this->organizationConfig->isCustomStreetAddress($this->getStoreId())) {
            return $this->organizationConfig->getCustomStreetAddress($this->getStoreId());
        }

        return $this->store->getConfig(StoreInformation::XML_PATH_STORE_INFO_STREET_LINE1)
            . ' '
            . $this->store->getConfig(StoreInformation::XML_PATH_STORE_INFO_STREET_LINE2);
    }

    public function getAddressRegion(): string
    {
        if ($this->organizationConfig->isCustomAddressRegion($this->getStoreId())) {
            return $this->organizationConfig->getCustomAddressRegion($this->getStoreId());
        }

        $regionId = $this->store->getConfig(StoreInformation::XML_PATH_STORE_INFO_REGION_CODE);

        return (string)$this->regionFactory->create()->load($regionId)->getCode();
    }

    public function getLogoUrl(): string
    {
        // fix since Magento_Theme v101.1.4
        if (class_exists('Magento\Theme\ViewModel\Block\Html\Header\LogoPathResolver') && !$this->logo->getData('logoPathResolver')) {
            $logoPathResolver = ObjectManager::getInstance()->get('Magento\Theme\ViewModel\Block\Html\Header\LogoPathResolver');
            $this->logo->setData('logoPathResolver', $logoPathResolver);
        }

        return (string)$this->logo->getLogoSrc();
    }

    public function getBaseUrl(): string
    {
        return (string)$this->context->getUrlBuilder()->getBaseUrl();
    }

    public function getSocialLinks(): array
    {
        return $this->organizationConfig->getSocialLinks($this->getStoreId());
    }

    public function getContactPoints(): array
    {
        return $this->organizationConfig->getContactPoints($this->getStoreId());
    }

    private function getStoreId(): int
    {
        if (!isset($this->store)) {
            return Store::DEFAULT_STORE_ID;
        }

        if (!isset($this->storeId)) {
            $this->storeId = (int)$this->store->getStoreId();
        }

        return $this->storeId;
    }

    private function canShow(): bool
    {
        if (!$this->organizationConfig->isRsEnabled($this->getStoreId())) {
            return false;
        }

        $displayScope = $this->organizationConfig->getDisplayScope($this->getStoreId());

        if (
            $displayScope == OrganizationConfig::DISPLAY_SCOPE_HOME
            && $this->getRequest()->getFullActionName() == 'cms_index_index'
        ) {
            return true;
        }

        if ($this->getRequest()->getRouteName() == 'cms') {
            if ($displayScope == OrganizationConfig::DISPLAY_SCOPE_ALL) {
                return true;
            }

            if ($displayScope == OrganizationConfig::DISPLAY_SCOPE_SPECIFIC) {
                $cmsPages = $this->organizationConfig->getCmsPages($this->getStoreId());

                $cmsPageId = $this->_request->getParam('page_id') ?? $this->_request->getParam('id');

                if (in_array($cmsPageId, $cmsPages)) {
                    return true;
                }
            }
        }

        return false;
    }
}

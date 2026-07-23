<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
namespace Secomm\AddressDropdown\Block\Adminhtml\Country;

use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\View\Element\Template;
use Magento\Framework\Locale\ListsInterface;
use Magento\Directory\Model\CountryFactory;

/**
 * Get Information of Country
 */
class Information extends Template
{
    /** @var string */
    protected $_template = 'Secomm_AddressDropdown::country/information.phtml';

    public function __construct(
        Template\Context $context,
        protected DataPersistorInterface $dataPersistor,
        protected ListsInterface $localeLists,
        protected CountryFactory $countryFactory,
        array $data = []
    )
    {
        parent::__construct($context, $data);
    }

    /**
     * Return Country ID
     * @return mixed|null
     */
    protected function getCountryId()
    {
        return $this->dataPersistor->get('country_id');
    }

    /**
     * @return null
     */
    public function getCountry()
    {
        try {
            $countryId = $this->getCountryId();
            if (isset($countryId)) {
                return $this->countryFactory->create()->load($countryId);
            }
            return null;
        } catch (\Exception $exception) {
            return null;
        }
    }

    /**
     * @param string $locale
     * @return string|null
     * @throws \Zend_Db_Statement_Exception
     */
    public function getCountryName($locale = 'en_US'): ?string
    {
        $countryName = $this->localeLists->getCountryTranslation($this->getCountryId(), $locale);
        if (!empty($countryName)) {
            return $countryName;
        }
        return null;
    }
}

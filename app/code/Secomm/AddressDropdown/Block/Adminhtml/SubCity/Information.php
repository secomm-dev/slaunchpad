<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\AddressDropdown\Block\Adminhtml\SubCity;

use Exception;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Locale\ListsInterface;
use Magento\Framework\View\Element\Template;
use Secomm\AddressDropdown\Api\Data\SubCityInterface;
use Secomm\AddressDropdown\Helper\Data;
use Secomm\AddressDropdown\Model\ResourceModel\SubCityNameModel\SubCityNameCollectionFactory;
use Secomm\AddressDropdown\Model\ResourceModel\SubCityResource as SubCityModelResourceModel;
use Secomm\AddressDropdown\Model\SubCityModel;
use Secomm\AddressDropdown\Model\SubCityModelFactory;

/**
 * Get Information of SubCity
 */
class Information extends Template
{
    /** @var string */
    protected $_template = 'Secomm_AddressDropdown::subcity/information.phtml';

    public function __construct(
        Template\Context                       $context,
        protected DataPersistorInterface       $dataPersistor,
        protected ListsInterface               $localeLists,
        protected SubCityModelFactory          $subCityModelFactory,
        protected SubCityModelResourceModel    $subCityResourceModel,
        protected SubCityNameCollectionFactory $collectionFactory,
        protected Data                         $dataHelper,
        array                                  $data = []
    )
    {
        parent::__construct($context, $data);
    }

    /**
     * @return SubCityModel|null
     */
    public function getSubCity(): ?SubCityModel
    {
        try {
            $subCityId = $this->getSubCityId();
            if (isset($subCityId)) {
                $subCityModel = $this->subCityModelFactory->create();
                $this->subCityResourceModel->load($subCityModel, $subCityId, SubCityInterface::SUB_CITY_ID);
                return $subCityModel;
            }
            return null;
        } catch (Exception $exception) {
            return null;
        }
    }

    /**
     * Return Country ID
     * @return mixed|null
     */
    protected function getSubCityId(): mixed
    {
        return $this->_request->getParam(SubCityInterface::SUB_CITY_ID);
    }


    /**
     * @return array|null
     */
    public function getAllSubCityNamesBySubCityId(): ?array
    {
        try {
            $subCityId = $this->getSubCityId();
            $subCityNamesCollectData = $this->collectionFactory->create();

            $subCityNamesCollectData->addFieldToFilter(
                SubCityInterface::SUB_CITY_ID,
                ['eq' => $subCityId]
            );
            return $subCityNamesCollectData->getData();
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * @return string
     */
    public function getBackUrl(): string
    {
        return $this->getUrl('*/subcity/index', [
            SubCityInterface::CITY_ID => $this->dataHelper->getCityIdBySubCityId($this->getSubCityId())
        ]);
    }
}

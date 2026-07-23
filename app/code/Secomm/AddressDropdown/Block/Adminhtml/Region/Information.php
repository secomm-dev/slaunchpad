<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\AddressDropdown\Block\Adminhtml\Region;

use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\View\Element\Template;
use Magento\Directory\Model\RegionFactory;
use Magento\Directory\Model\ResourceModel\Region as RegionResource;
use Secomm\AddressDropdown\Api\Data\RegionInterface;
use Secomm\AddressDropdown\Helper\Data as HelperData;

/**
 * Get Information of Region
 */
class Information extends Template
{
    /** @var string */
    protected $_template = 'Secomm_AddressDropdown::region/information.phtml';

    public function __construct(
        Template\Context                 $context,
        protected DataPersistorInterface $dataPersistor,
        protected RegionFactory          $regionFactory,
        protected RegionResource         $regionResource,
        protected HelperData             $helperData,
        array                            $data = []
    )
    {
        parent::__construct($context, $data);
    }

    /**
     * Return region ID
     * @return mixed|null
     */
    protected function getRegionId()
    {
        return $this->dataPersistor->get(RegionInterface::REGION_ID);
    }

    /**
     * Return region data
     * @return \Magento\Directory\Model\Region|null
     */
    public function getRegion()
    {
        try {
            $regionId = $this->getRegionId();
            if (isset($regionId)) {
                $regionModel = $this->regionFactory->create();
                $this->regionResource->load($regionModel, $regionId);
                return $regionModel;
            }
            return null;
        } catch (\Exception $exception) {
            return null;
        }
    }

    /**
     * @return array|null
     */
    public function getAllRegionNamesByRegionId() : ?array
    {
        return $this->helperData->getAllRegionNamesByRegionId($this->getRegionId());
    }
}

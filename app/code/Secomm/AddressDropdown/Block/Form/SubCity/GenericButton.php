<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\AddressDropdown\Block\Form\SubCity;

use Magento\Backend\Block\Widget\Context;
use Magento\Framework\UrlInterface;
use Secomm\AddressDropdown\Api\Data\CityInterface;
use Secomm\AddressDropdown\Api\Data\SubCityInterface;
use Secomm\AddressDropdown\Helper\Data;

/**
 * Generic (form) button for SubCity entity.
 */
class GenericButton
{
    /**
     * @var Context
     */
    private Context $context;

    /**
     * @var UrlInterface
     */
    private UrlInterface $urlBuilder;

    /**
     * @var Data
     */
    private Data $data;

    /**
     * @param Data $data
     * @param Context $context
     */
    public function __construct(
        Data $data,
        Context $context
    )
    {
        $this->context = $context;
        $this->data = $data;
        $this->urlBuilder = $context->getUrlBuilder();
    }

    /**
     * Get SubCity entity id.
     *
     * @return int
     */
    public function getSubCityId(): int
    {
        return (int)$this->context->getRequest()->getParam(SubCityInterface::SUB_CITY_ID);
    }

    /**
     * Get SubCity entity id.
     *
     * @return int
     */
    public function getCityId(): int
    {
        if (!(int)$this->context->getRequest()->getParam(SubCityInterface::CITY_ID)) {
            return $this->data->getCityIdBySubCityId($this->getSubCityId());
        }
        return (int)$this->context->getRequest()->getParam(SubCityInterface::CITY_ID);
    }

    /**
     * Get SubCity entity id.
     *
     * @return int
     */
    public function getRegionId(): int
    {
        if (!(int)$this->context->getRequest()->getParam(CityInterface::REGION_ID)) {
            return $this->data->getRegionIdByCityId($this->getCityId());
        }
        return (int)$this->context->getRequest()->getParam(CityInterface::REGION_ID);
    }

    /**
     * Wrap button specific options to settings array.
     *
     * @param string $label
     * @param string $class
     * @param string $onclick
     * @param array $dataAttribute
     * @param int $sortOrder
     *
     * @return array
     */
    protected function wrapButtonSettings(
        string $label,
        string $class,
        string $onclick = '',
        array  $dataAttribute = [],
        int    $sortOrder = 0
    ): array
    {
        return [
            'label' => $label,
            'on_click' => $onclick,
            'data_attribute' => $dataAttribute,
            'class' => $class,
            'sort_order' => $sortOrder
        ];
    }

    /**
     * Get url.
     *
     * @param string $route
     * @param array $params
     *
     * @return string
     */
    protected function getUrl(string $route, array $params = []): string
    {
        return $this->urlBuilder->getUrl($route, $params);
    }
}

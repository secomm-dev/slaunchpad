<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\AddressDropdown\Block\Form\Region;

use Magento\Backend\Block\Widget\Context;
use Magento\Framework\UrlInterface;
use Secomm\AddressDropdown\Api\Data\CityInterface;
use Secomm\AddressDropdown\Api\Data\RegionInterface;
use Secomm\AddressDropdown\Helper\Data;

/**
 * Generic (form) button for City entity.
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
     * @param Context $context
     * @param Data $data
     */
    public function __construct(
        Context $context,
        Data $data
    )
    {
        $this->context = $context;
        $this->data = $data;
        $this->urlBuilder = $context->getUrlBuilder();
    }

    /**
     * Get City entity id.
     *
     * @return int
     */
    public function getRegionId(): int
    {
        return (int)$this->context->getRequest()->getParam(CityInterface::REGION_ID);
    }

    /**
     * Get Country id.
     *
     * @return string
     */
    public function getCountryId(): string
    {
        if (!(string)$this->context->getRequest()->getParam(RegionInterface::COUNTRY_ID)) {
            return $this->data->getCountryIdByRegionId($this->getRegionId());
        }
        return (string)$this->context->getRequest()->getParam(RegionInterface::COUNTRY_ID);
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

    protected function isRegionDefault() {
        $flag = false;
        if (!$this->getRegionId()) {
            return [];
        }
        $regionModel = $this->data->getRegionById($this->getRegionId());
        if ($regionModel && $regionModel->getId()) {
            if ((int)$regionModel->getIsDefault()) {
                $flag = true;
            }
        }
        return $flag;
    }
}

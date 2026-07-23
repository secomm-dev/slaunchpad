<?php
/**
 * Mageplaza
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Mageplaza.com license that is
 * available through the world-wide-web at this URL:
 * https://www.mageplaza.com/LICENSE.txt
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this extension to newer
 * version in the future.
 *
 * @category    Mageplaza
 * @package     Mageplaza_Lookbook
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\Lookbook\Helper;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\UrlInterface;
use Mageplaza\Core\Helper\AbstractData;

/**
 * Class Data
 * @package Mageplaza\Lookbook\Helper
 */
class Data extends AbstractData
{
    const CONFIG_MODULE_PATH = 'mplookbook';

    /**
     * @param null $store
     *
     * @return array|mixed
     */
    public function getSliderDesignConfig($store = null)
    {
        return $this->getModuleConfig('mplookbook_design', $store);
    }

    /**
     * @param null $storeId
     *
     * @return bool|string
     * @throws NoSuchEntityException
     */
    public function getMarkerIcon($storeId = null)
    {
        $icon = $this->getConfigGeneral('marker_icon', $storeId);
        if (!$icon) {
            return false;
        }

        return $this->getMediaHelper()->resizeImage($icon, $this->getMarkerWidth($storeId));
    }

    /**
     * @return Media
     */
    public function getMediaHelper()
    {
        return $this->objectManager->get(Media::class);
    }

    /**
     * @param null $storeId
     *
     * @return array|mixed
     */
    public function getMarkerWidth($storeId = null)
    {
        return $this->getConfigGeneral('marker_width', $storeId);
    }

    /**
     * @param null $storeId
     *
     * @return array|mixed
     */
    public function getMarkerHeight($storeId = null)
    {
        return $this->getConfigGeneral('marker_height', $storeId);
    }

    /**
     * get images base url
     *
     * @return string
     */
    public function getBaseImageUrl()
    {
        return $this->_urlBuilder->getBaseUrl(['_type' => UrlInterface::URL_TYPE_MEDIA]) . Media::TEMPLATE_MEDIA_PATH . '/';
    }

    /**
     * @param $route
     * @param array $params
     *
     * @return string
     */
    public function getUrl($route, $params = [])
    {
        return $this->_getUrl($route, $params);
    }

    /**
     * @param null $store
     *
     * @return string
     */
    public function getDesignConfig($store = null)
    {
        $designConfig = [];
        $designConfig['mobileFirst'] = true;
        foreach ($this->getModuleConfig('mplookbook_design', $store) as $key => $value) {
            if ($key === 'autoplaySpeed') {
                $designConfig[$key] = (int)$value;
            } else {
                $designConfig[$key] = $key === 'lazyLoad' ? $value : (boolean)$value;
            }
        }

        return self::jsonEncode($designConfig);
    }
}

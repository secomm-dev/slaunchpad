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

namespace Mageplaza\Lookbook\Block\Adminhtml\Lookbook\Edit\Render;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Image\Adapter\AdapterInterface;
use Magento\Framework\Image\AdapterFactory;
use Magento\Framework\View\Element\Template;
use Mageplaza\Lookbook\Helper\Data;
use Mageplaza\Lookbook\Helper\Media;

/**
 * Class Editor
 * @package Mageplaza\Lookbook\Block\Adminhtml\Lookbook\Edit\Render
 */
class Editor extends Template
{

    /**
     * @var AdapterFactory
     */
    protected $imageFactory;
    /**
     * @var Data
     */
    protected $helperData;

    /**
     * Editor constructor.
     *
     * @param Template\Context $context
     * @param AdapterFactory $imageFactory
     * @param Data $helperData
     * @param array $data
     */
    public function __construct(
        Template\Context $context,
        AdapterFactory $imageFactory,
        Data $helperData,
        array $data = []
    ) {
        $this->imageFactory = $imageFactory;
        $this->helperData = $helperData;

        parent::__construct($context, $data);
    }

    /**
     * @param $src
     *
     * @return string
     */
    public function getImageUrl($src)
    {
        return $this->helperData->getBaseImageUrl() . $src;
    }

    /**
     * @param string $src
     *
     * @return AdapterInterface
     */
    public function getImageInfo($src)
    {
        $absolutePath = $this->_filesystem->getDirectoryRead(DirectoryList::MEDIA)->getAbsolutePath(Media::TEMPLATE_MEDIA_PATH . '/' . $src);
        $image = $this->imageFactory->create();
        $image->open($absolutePath);

        return $image;
    }

    /**
     * @return string
     * @throws NoSuchEntityException
     */
    public function getMarkerIconUrl()
    {
        $params = [
            'area' => 'adminhtml'
        ];

        return $this->helperData->getMarkerIcon() ?:
            $this->getIconUrl('Mageplaza_Lookbook::js/plugins/icon/marker.png', $params);
    }

    /**
     * @param $icon
     * @param array $params
     *
     * @return string
     */
    public function getIconUrl($icon, array $params = [])
    {
        $icon = $this->_assetRepo->createAsset($icon, $params);

        return $icon->getUrl();
    }
}

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

namespace Mageplaza\Lookbook\Block;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\DesignInterface;
use Magento\Framework\View\Element\Template;
use Magento\Widget\Block\BlockInterface;
use Mageplaza\Lookbook\Helper\Data;
use Mageplaza\Lookbook\Model\ResourceModel\Lookbook\Collection;
use Mageplaza\Lookbook\Model\ResourceModel\Lookbook\CollectionFactory;
use Mageplaza\Lookbook\Model\ResourceModel\Slider as ResourceModel;
use Mageplaza\Lookbook\Model\Slider;
use Mageplaza\Lookbook\Model\SliderFactory;

/**
 * Class Widget
 * @package Mageplaza\Lookbook\Block
 */
class Widget extends Template implements BlockInterface
{
    protected $_template = 'Mageplaza_Lookbook::slider.phtml';

    /**
     * @var Data
     */
    protected $helperData;
    /**
     * @var SliderFactory
     */
    protected $sliderFactory;
    /**
     * @var ResourceModel
     */
    protected $resourceModel;
    /**
     * @var CollectionFactory
     */
    protected $collectionFactory;

    /**
     * @var Slider
     */
    protected $slider;

    /**
     * @var DesignInterface
     */
    protected $design;

    /**
     * Widget constructor.
     *
     * @param Template\Context $context
     * @param Data $helperData
     * @param SliderFactory $sliderFactory
     * @param ResourceModel $resourceModel
     * @param CollectionFactory $collectionFactory
     * @param DesignInterface $design
     * @param array $data
     */
    public function __construct(
        Template\Context $context,
        Data $helperData,
        SliderFactory $sliderFactory,
        ResourceModel $resourceModel,
        CollectionFactory $collectionFactory,
        DesignInterface $design,
        array $data = []
    ) {
        $this->helperData        = $helperData;
        $this->sliderFactory     = $sliderFactory;
        $this->resourceModel     = $resourceModel;
        $this->collectionFactory = $collectionFactory;
        $this->design            = $design;

        parent::__construct($context, $data);
    }

    /**
     * @return array|Collection
     * @throws NoSuchEntityException
     */
    public function getLookbookCollection()
    {
        $model = $this->getSlider();
        if (!$model || !$this->helperData->isEnabled()) {
            return [];
        }

        /** @var Collection $collection */
        $collection = $this->collectionFactory->create();
        $collection->join(
            ['slider_lookbook' => $collection->getTable('mageplaza_lookbookslider_slider_lookbook')],
            'main_table.lookbook_id=slider_lookbook.lookbook_id AND slider_lookbook.slider_id=' . $model->getId(),
            ['position']
        );
        $collection->addActiveFilter($this->_storeManager->getStore()->getId());
        $collection->addOrder('position', 'ASC');

        return $collection;
    }

    /**
     * @return Slider|null
     */
    public function getSlider()
    {
        $sliderId = $this->getData('slider_id');

        if (!$sliderId || !$this->helperData->isEnabled()) {
            return null;
        }
        if ($this->slider !== null) {
            return $this->slider;
        }
        /** @var Slider $model */
        $model = $this->sliderFactory->create();
        $this->resourceModel->load($model, $sliderId);
        if (!$model->getId()) {
            return null;
        }
        if (!$model->getStatus()) {
            return null;
        }
        $this->slider = $model;

        return $this->slider;
    }

    /**
     * @param Collection $collection
     *
     * @return int
     */
    public function usePopupCss(Collection $collection)
    {
        return $collection->addFieldToFilter('marker_type', 0)->getSize();
    }

    /**
     * @param string $imageSrc
     *
     * @return string
     */
    public function getImageUrl($imageSrc)
    {
        return $this->helperData->getBaseImageUrl() . $imageSrc;
    }

    /**
     * @param Slider $slider
     *
     * @return boolean
     */
    public function isLazyLoad(Slider $slider)
    {
        if ($slider->getData('design')) {
            return $slider->getData('lazyLoad');
        }

        return $this->helperData->getModuleConfig('mplookbook_design/lazyLoad');
    }

    /**
     * @param Slider $slider
     *
     * @return string
     */
    public function getSliderOptions(Slider $slider)
    {
        if ($slider->getDesign()) {
            $settings = [
                'infinite',
                'arrows',
                'dots',
                'lazyLoad',
                'autoplay',
                'autoplaySpeed',
                'pauseOnHover'
            ];
            $data = [];
            $data['mobileFirst'] = true;
            foreach ($settings as $setting) {
                if ($setting === 'autoplaySpeed') {
                    $data[$setting] = (int)$slider->getData($setting);
                } elseif ($setting === 'lazyLoad') {
                    $data[$setting] = $slider->getData($setting);
                } else {
                    $data[$setting] = (boolean)$slider->getData($setting);
                }
            }

            return Data::jsonEncode($data);
        }

        return $this->helperData->getDesignConfig();
    }

    /**
     * @param Slider $slider
     *
     * @return string
     */
    public function getCustomCss(Slider $slider)
    {
        return $slider->getCustomCss();
    }

    /**
     * Get relevant path to template
     *
     * @return string
     */
    public function getTemplate()
    {
        if ($this->isHyvaTheme())  {
            return 'Mageplaza_Lookbook::hyva/slider.phtml';
        }

        return parent::getTemplate();
    }

    /**
     * @return bool
     */
    public function isHyvaTheme()
    {
        return $this->helperData->checkHyvaTheme();
    }
}

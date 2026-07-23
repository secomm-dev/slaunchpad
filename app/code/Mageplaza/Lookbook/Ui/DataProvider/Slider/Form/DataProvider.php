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

namespace Mageplaza\Lookbook\Ui\DataProvider\Slider\Form;

use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Framework\Exception\NotFoundException;
use Magento\Framework\Registry;
use Magento\Ui\DataProvider\Modifier\PoolInterface;
use Magento\Ui\DataProvider\ModifierPoolDataProvider;
use Mageplaza\Lookbook\Helper\Data;
use Mageplaza\Lookbook\Model\Lookbook;
use Mageplaza\Lookbook\Model\LookbookFactory;
use Mageplaza\Lookbook\Model\ResourceModel\Slider as ResourceModel;
use Mageplaza\Lookbook\Model\ResourceModel\Slider\CollectionFactory;
use Mageplaza\Lookbook\Model\Slider;

/**
 * Class DataProvider
 * @package Mageplaza\Lookbook\Ui\DataProvider\Slider\Form
 */
class DataProvider extends ModifierPoolDataProvider
{
    /**
     * @var array
     */
    protected $loadedData;
    /**
     * @var Registry
     */
    private $registry;

    /**
     * @var Slider
     */
    private $slider;
    /**
     * @var ResourceModel
     */
    private $resourceModel;
    /**
     * @var LookbookFactory
     */
    private $lookbookFactory;
    /**
     * @var Data
     */
    private $helperData;
    /**
     * @var Status
     */
    private $status;

    /**
     * DataProvider constructor.
     *
     * @param string $name
     * @param string $primaryFieldName
     * @param string $requestFieldName
     * @param Registry $registry
     * @param CollectionFactory $collectionFactory
     * @param ResourceModel $resourceModel
     * @param LookbookFactory $lookbookFactory
     * @param Data $helperData
     * @param Status $status
     * @param array $meta
     * @param array $data
     * @param PoolInterface|null $pool
     */
    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        Registry $registry,
        CollectionFactory $collectionFactory,
        ResourceModel $resourceModel,
        LookbookFactory $lookbookFactory,
        Data $helperData,
        Status $status,
        array $meta = [],
        array $data = [],
        ?PoolInterface $pool = null
    ) {
        $this->collection = $collectionFactory->create();
        $this->registry = $registry;
        $this->resourceModel = $resourceModel;
        $this->lookbookFactory = $lookbookFactory;
        $this->helperData = $helperData;

        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data, $pool);
        $this->status = $status;
    }

    /**
     * {@inheritdoc}
     * @since 101.0.0
     */
    public function getData()
    {
        if (isset($this->loadedData)) {
            return $this->loadedData;
        }
        $items = $this->collection->getItems();
        /** @var Slider $model */
        foreach ($items as $model) {
            $sliderId = $model->getId();
            $modelData = $model->getData();
            $modelData['lookbook']['data']['lookbook']['assigned_lookbooks'] = $this->getAssignedLookbooksData($sliderId);
            $this->loadedData[$sliderId] = $modelData;
        }

        return $this->loadedData;
    }

    /**
     * @param int $sliderId
     *
     * @return array
     */
    protected function getAssignedLookbooksData(int $sliderId)
    {
        $data = [];
        if ($this->resourceModel->hasLookbookLinks($sliderId)) {
            foreach ($this->resourceModel->getLookbooksData($sliderId) as $lookbookData) {
                $lookbook = $this->lookbookFactory->create()->load($lookbookData['lookbook_id']);
                $data[] = $this->fillData($lookbook, $lookbookData);
            }
        }

        return $data;
    }

    /**
     * @param Lookbook $lookbook
     * @param array $data
     *
     * @return array
     */
    protected function fillData(Lookbook $lookbook, array $data)
    {
        return [
            'lookbook_id' => $lookbook->getId(),
            'image' => $this->helperData->getBaseImageUrl() . $lookbook->getImage(),
            'name' => $lookbook->getName(),
            'status' => $this->status->getOptionText($lookbook->getStatus()) ? $this->status->getOptionText($lookbook->getStatus()) : 'Disabled',
            'position' => $data['position'],
        ];
    }

    /**
     * @return Slider
     * @throws NotFoundException
     */
    protected function getSlider()
    {
        if ($this->slider !== null) {
            return $this->slider;
        }

        if ($slider = $this->registry->registry('mplookbook_slider')) {
            return $this->slider = $slider;
        }

        throw new NotFoundException(__("The slider wasn't registered."));
    }
}

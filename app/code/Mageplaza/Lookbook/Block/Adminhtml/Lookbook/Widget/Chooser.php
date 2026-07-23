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

namespace Mageplaza\Lookbook\Block\Adminhtml\Lookbook\Widget;

use Magento\Backend\Block\Template\Context;
use Magento\Backend\Block\Widget\Grid\Extended;
use Magento\Backend\Helper\Data;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\Exception\LocalizedException;
use Mageplaza\Lookbook\Model\LookbookFactory;
use Mageplaza\Lookbook\Model\ResourceModel\Lookbook\CollectionFactory;

/**
 * Class Chooser
 * @package Mageplaza\Lookbook\Block\Adminhtml\Lookbook\Widget
 */
class Chooser extends Extended
{

    /**
     * @var LookbookFactory
     */
    protected $lookbookFactory;
    /**
     * @var CollectionFactory
     */
    protected $collectionFactory;

    /**
     * Chooser constructor.
     *
     * @param Context $context
     * @param Data $backendHelper
     * @param LookbookFactory $lookbookFactory
     * @param CollectionFactory $collectionFactory
     * @param array $data
     */
    public function __construct(
        Context $context,
        Data $backendHelper,
        LookbookFactory $lookbookFactory,
        CollectionFactory $collectionFactory,
        array $data = []
    ) {
        $this->lookbookFactory = $lookbookFactory;
        $this->collectionFactory = $collectionFactory;

        parent::__construct($context, $backendHelper, $data);
    }

    /**
     * Block construction, prepare grid params
     *
     * @return void
     */
    protected function _construct()
    {
        parent::_construct();
        $this->setDefaultSort('lookbook_id');
        $this->setDefaultDir('ASC');
        $this->setUseAjax(true);
        $this->setDefaultFilter(['chooser_is_active' => '1']);
    }

    /**
     * Prepare chooser element HTML
     *
     * @param AbstractElement $element Form Element
     *
     * @return AbstractElement
     * @throws LocalizedException
     */
    public function prepareElementHtml(AbstractElement $element)
    {
        $uniqId = $this->mathRandom->getUniqueHash($element->getId());
        $sourceUrl = $this->getUrl('mplookbook/lookbook_widget/chooser', ['uniq_id' => $uniqId]);

        $chooser = $this->getLayout()->createBlock(
            \Magento\Widget\Block\Adminhtml\Widget\Chooser::class
        )->setElement(
            $element
        )->setConfig(
            $this->getConfig()
        )->setFieldsetId(
            $this->getFieldsetId()
        )->setSourceUrl(
            $sourceUrl
        )->setUniqId(
            $uniqId
        );

        if ($element->getValue()) {
            $model = $this->lookbookFactory->create()->load($element->getValue());
            if ($model->getId()) {
                $chooser->setLabel($this->escapeHtml($model->getName()));
            }
        }

        $element->setData('after_element_html', $chooser->toHtml());

        return $element;
    }

    /**
     * Grid Row JS Callback
     *
     * @return string
     */
    public function getRowClickCallback()
    {
        $chooserJsObject = $this->getId();
        $js = '
            function (grid, event) {
                var trElement = Event.findElement(event, "tr");
                var blockId = trElement.down("td").innerHTML.replace(/^\s+|\s+$/g,"");
                var blockTitle = trElement.down("td").next().innerHTML;
                ' .
            $chooserJsObject .
            '.setElementValue(blockId);
                ' .
            $chooserJsObject .
            '.setElementLabel(blockTitle);
                ' .
            $chooserJsObject .
            '.close();
            }
        ';

        return $js;
    }

    /**
     * Prepare Lookbook collection
     *
     * @return Extended
     */
    protected function _prepareCollection()
    {
        $this->setCollection($this->collectionFactory->create());

        return parent::_prepareCollection();
    }

    /**
     * Prepare columns for Lookbook grid
     *
     * @return Extended
     */
    protected function _prepareColumns()
    {
        $this->addColumn(
            'chooser_id',
            ['header' => __('ID'), 'align' => 'right', 'index' => 'lookbook_id', 'width' => 50]
        );

        $this->addColumn('chooser_title', ['header' => __('Name'), 'align' => 'left', 'index' => 'name']);

        $this->addColumn(
            'chooser_is_active',
            [
                'header' => __('Status'),
                'index' => 'status',
                'type' => 'options',
                'options' => [0 => __('Disabled'), 1 => __('Enabled')]
            ]
        );

        return parent::_prepareColumns();
    }

    /**
     * Get grid url
     *
     * @return string
     */
    public function getGridUrl()
    {
        return $this->getUrl('mplookbook/lookbook_widget/chooser', ['_current' => true]);
    }
}

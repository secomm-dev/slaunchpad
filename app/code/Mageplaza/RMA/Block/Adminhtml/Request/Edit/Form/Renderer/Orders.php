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
 * @package     Mageplaza_RMA
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\RMA\Block\Adminhtml\Request\Edit\Form\Renderer;

use Exception;
use Magento\Backend\Block\Template\Context;
use Magento\Backend\Block\Widget\Grid\Extended;
use Magento\Backend\Helper\Data;
use Magento\Sales\Model\ResourceModel\Order\Collection;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use Mageplaza\RMA\Model\Config\Source\RMARequest\Orders as RMAOrders;

/**
 * Class Orders
 * @package Mageplaza\RMA\Block\Adminhtml\Request\Edit\Form\Renderer
 */
class Orders extends Extended
{
    /**
     * @var string
     */
    protected $_template = 'Mageplaza_RMA::request/form/order/grid.phtml';

    /**
     * @var CollectionFactory
     */
    protected $_orderColFactory;

    /**
     * @var RMAOrders
     */
    protected $_RMAOrders;

    /**
     * Products constructor.
     *
     * @param Context $context
     * @param Data $backendHelper
     * @param CollectionFactory $orderColFactory
     * @param RMAOrders $RMAOrders
     * @param array $data
     */
    public function __construct(
        Context           $context,
        Data              $backendHelper,
        CollectionFactory $orderColFactory,
        RMAOrders         $RMAOrders,
        array             $data = []
    )
    {
        parent::__construct($context, $backendHelper, $data);

        $this->_orderColFactory = $orderColFactory;
        $this->_RMAOrders       = $RMAOrders;
    }

    /**
     * _construct
     * @return void
     */
    protected function _construct()
    {
        parent::_construct();
        $this->setId('requestOrdersGrid');
        $this->setDefaultSort('entity_id');
        $this->setDefaultDir('ASC');
        $this->setSaveParametersInSession(false);
        $this->setUseAjax(true);
    }

    /**
     * prepare collection
     * @return Extended
     */
    protected function _prepareCollection()
    {
        /** @var Collection $collection */
        $collection = $this->_orderColFactory->create()->addAttributeToSelect('*');
        $orderIds   = $this->_RMAOrders->orderListID();
        $collection->addFieldToFilter('entity_id', ['in' => $orderIds]);
        $this->setCollection($collection);

        return parent::_prepareCollection();
    }

    /**
     * @return Extended
     * @throws Exception
     */
    protected function _prepareColumns()
    {
        $this->addColumn('entity_id', [
            'header'           => __('Order ID'),
            'type'             => 'number',
            'index'            => 'entity_id',
            'header_css_class' => 'col-id',
            'column_css_class' => 'col-id',
        ]);
        $this->addColumn('increment_id', [
            'header'           => __('Increment ID'),
            'index'            => 'increment_id',
            'width'            => '50px'
        ]);
        $this->addColumn('customer_email', [
            'header'           => __('Customer Email'),
            'index'            => 'customer_email',
            'width'            => '50px',
        ]);
        $this->addColumn('base_grand_total', [
            'header'           => __('Grand Total (Base)'),
            'type'             => 'currency',
            'index'            => 'base_grand_total',
            'width'            => '50px',
        ]);
        $this->addColumn('grand_total', [
            'header'           => __('Grand Total (Purchased)'),
            'type'             => 'currency',
            'currency'         => 'order_currency_code',
            'index'            => 'base_grand_total',
            'width'            => '50px',
        ]);
        $this->addColumn('position', [
            'header'           => __('Position'),
            'name'             => 'position',
            'header_css_class' => 'hidden',
            'column_css_class' => 'hidden',
            'validate_class'   => 'validate-number',
            'index'            => 'position',
            'editable'         => true
        ]);

        return parent::_prepareColumns();
    }

    /**
     * @return string
     */
    public function getGridUrl()
    {
        return $this->getUrl('mprma/request/order_grid');
    }

    /**
     * @param object $row
     *
     * @return string
     * @SuppressWarnings(Unused)
     */
    public function getRowUrl($row)
    {
        return '';
    }
}

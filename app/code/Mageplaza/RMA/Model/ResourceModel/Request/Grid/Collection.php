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

namespace Mageplaza\RMA\Model\ResourceModel\Request\Grid;

use Magento\Backend\Model\Session;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Data\Collection\Db\FetchStrategyInterface as FetchStrategy;
use Magento\Framework\Data\Collection\EntityFactoryInterface as EntityFactory;
use Magento\Framework\DB\Select;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Element\UiComponent\DataProvider\SearchResult;
use Mageplaza\RMA\Model\ResourceModel\Request;
use Psr\Log\LoggerInterface as Logger;
use Zend_Db_Expr;

/**
 * Class Collection
 * @package Mageplaza\RMA\Model\ResourceModel\Request\Grid
 */
class Collection extends SearchResult
{
    /**
     * @var RequestInterface
     */
    protected $_request;

    /**
     * @var Session
     */
    protected $_backendSession;

    /**
     * Collection constructor.
     *
     * @param EntityFactory $entityFactory
     * @param Logger $logger
     * @param FetchStrategy $fetchStrategy
     * @param EventManager $eventManager
     * @param RequestInterface $request
     * @param Session $backendSession
     * @param string $mainTable
     * @param string $resourceModel
     *
     * @throws LocalizedException
     */
    public function __construct(
        EntityFactory $entityFactory,
        Logger $logger,
        FetchStrategy $fetchStrategy,
        EventManager $eventManager,
        RequestInterface $request,
        Session $backendSession,
        $mainTable = 'mageplaza_rma_request',
        $resourceModel = Request::class
    ) {
        $this->_request = $request;
        $this->_backendSession = $backendSession;

        parent::__construct(
            $entityFactory,
            $logger,
            $fetchStrategy,
            $eventManager,
            $mainTable,
            $resourceModel
        );
    }

    /**
     * @return $this
     */
    protected function _initSelect()
    {
        parent::_initSelect();
        $this->_addRequestItems();
        if ($this->_request->getParam('order_id')) {
            $this->_backendSession->setData('mageplaza_current_order_id', $this->_request->getParam('order_id'));
        }

        if ($this->_request->getFullActionName() === 'mui_index_render'
            && $this->_request->getParam('namespace') === 'mageplaza_rma_order_view_request_grid') {
            $this->addFieldToFilter(
                'main_table.order_id',
                $this->_backendSession->getData('mageplaza_current_order_id')
            );
        }

        return $this;
    }

    /**
     * @param array|string $field
     * @param null $condition
     *
     * @return mixed
     */
    public function addFieldToFilter($field, $condition = null)
    {
        if ($field === 'store_filter') {
            $this->getSelect()->where("main_table.store_id = '{$condition['eq']}'");

            return $this;
        }
        if ($field === 'items') {
            $this->getSelect()->having("GROUP_CONCAT(`mprri`.`name`) LIKE '%{$condition['like']}%'");

            return $this;
        }
        if ($field === 'item_qty') {
            if (isset($condition['gteq'])) {
                $this->getSelect()->having("SUM(`mprri`.`qty_rma`) >= '{$condition['gteq']}'");
            }
            if (isset($condition['lteq'])) {
                $this->getSelect()->having("SUM(`mprri`.`qty_rma`) <= '{$condition['lteq']}'");
            }

            return $this;
        }
        if ($field === 'total_price') {
            $this->getSelect()->having("SUM(`mprri`.`price`) = '{$condition['like']}'");

            return $this;
        }
        if ($field === 'request_id') {
            $field = 'main_table.request_id';
        }
        if ($field === 'customer_email') {
            $field = 'main_table.customer_email';
        }
        if ($field === 'name') {
            $field = 'main_table.name';
        }

        return parent::addFieldToFilter($field, $condition);
    }

    /**
     * Add request items to grid
     *
     * @return $this
     */
    protected function _addRequestItems()
    {
        $this->getSelect()->joinLeft(
            ['mprri' => $this->getTable('mageplaza_rma_request_item')],
            'main_table.request_id = mprri.request_id',
            []
        )->columns([
            'items' => new Zend_Db_Expr('GROUP_CONCAT(`mprri`.`name`)'),
            'reason' => new Zend_Db_Expr('mprri.reason'),
            'solution' => new Zend_Db_Expr('mprri.solution'),
            'item_qty' => new Zend_Db_Expr('SUM(`mprri`.`qty_rma`)'),
            'total_price' => new Zend_Db_Expr('SUM(`mprri`.`price`)')
        ])->group('main_table.request_id');

        return $this;
    }

    /**
     * @return Select
     */
    public function getSelectCountSql()
    {
        $this->_renderFilters();
        $select = clone $this->getSelect();
        $select->reset(Select::ORDER);

        return $this->getConnection()->select()->from($select, 'COUNT(*)');
    }

    /**
     * Render sql select conditions
     *
     * @return  $this
     */
    protected function _renderFilters()
    {
        parent::_renderFilters();
        $select = $this->getSelect();
        $where = $select->getPart('where');
        foreach ($where as &$item) {
            if (strpos($item, '(`created_at`') !== false) {
                $item = str_replace('`created_at`', '`main_table`.`created_at`', $item);
            }
        }
        $select->setPart('where', $where);

        return $this;
    }
}

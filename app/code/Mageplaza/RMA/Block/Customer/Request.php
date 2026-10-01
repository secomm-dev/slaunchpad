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

namespace Mageplaza\RMA\Block\Customer;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Theme\Block\Html\Pager;
use Mageplaza\RMA\Helper\Data as HelperData;
use Mageplaza\RMA\Model\Request as RequestModel;
use Mageplaza\RMA\Model\ResourceModel\Request\Collection;
use Mageplaza\RMA\Model\ResourceModel\Request\CollectionFactory;

/**
 * Class Request
 * @package Mageplaza\RMA\Block\Customer
 */
class Request extends Template
{
    /**
     * @var HelperData
     */
    public $_helperData;

    /**
     * @var Collection
     */
    protected $_requestCollection;

    /**
     * @var CollectionFactory
     */
    protected $_requestColFactory;

    /**
     * Request constructor.
     *
     * @param Context $context
     * @param HelperData $helperData
     * @param CollectionFactory $requestColFactory
     * @param array $data
     */
    public function __construct(
        Context $context,
        HelperData $helperData,
        CollectionFactory $requestColFactory,
        array $data = []
    ) {
        $this->_helperData = $helperData;
        $this->_requestColFactory = $requestColFactory;

        parent::__construct($context, $data);
    }

    /**
     * @return $this|Template
     * @throws LocalizedException
     */
    protected function _prepareLayout()
    {
        if ($this->_helperData->checkHyvaTheme()) {
            $this->_template = 'Mageplaza_RMA::hyva/customer/request.phtml';
        }
        parent::_prepareLayout();
        if ($this->getRmaRequests()) {
            $pager = $this->getLayout()->createBlock(
                Pager::class,
                'mprma.request.history.pager'
            )->setCollection(
                $this->getRmaRequests()
            );
            $this->setChild('pager', $pager);
            $this->getRmaRequests()->load();
        }

        return $this;
    }

    /**
     * @return bool|Collection
     */
    public function getRmaRequests()
    {
        if (!$this->_helperData->isLoggedIn()) {
            return false;
        }
        if (!$this->_requestCollection) {
            $this->_requestCollection = $this->_requestColFactory->create()
                ->addOrderTable()
                ->addFieldToFilter('orders.customer_id', $this->_helperData->getCustomerId())
                ->setOrder(
                    'main_table.created_at',
                    'desc'
                );
        }

        return $this->_requestCollection;
    }

    /**
     * @return string
     */
    public function getPagerHtml()
    {
        return $this->getChildHtml('pager');
    }

    /**
     * @param string|int $requestId
     *
     * @return string
     */
    public function getRequestDetailsUrl($requestId)
    {
        return $this->getUrl('mprma/request/view', ['request_id' => $requestId]);
    }

    /**
     * @param RequestModel $request
     *
     * @return bool
     */
    public function canCancelRequest($request)
    {
        $status = $this->_helperData->getRequestConfig('cancel_status');
        $status = explode(',', $status ?: '');

        return in_array($request->getStatusId(), $status, true);
    }
}

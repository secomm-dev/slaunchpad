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

namespace Mageplaza\RMA\Block\Adminhtml\Request\Edit\Form;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\ResourceModel\Order\Item\Collection as ItemCollection;
use Mageplaza\RMA\Helper\Data as HelperData;
use Mageplaza\RMA\Model\Request;

/**
 * Class Items
 * @method Items setCurrentRequest($request)
 * @method Items setOrderedProducts($orderedProducts)
 * @method Items setOrder($order)
 * @method Request|bool getCurrentRequest()
 * @method ItemCollection getOrderedProducts()
 * @method Order getOrder()
 * @package Mageplaza\RMA\Block\Adminhtml\Request\Edit\Form
 */
class Items extends Template
{
    /**
     * @var HelperData
     */
    public $helperData;

    /**
     * Items constructor.
     *
     * @param Context $context
     * @param HelperData $helperData
     * @param array $data
     */
    public function __construct(
        Context $context,
        HelperData $helperData,
        array $data = []
    ) {
        $this->helperData = $helperData;

        parent::__construct($context, $data);
    }
}

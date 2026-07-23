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
 * @package     Mageplaza_OscUltimate
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\OscUltimate\Block\Adminhtml\Layout\ManageBlock;

use Magento\Backend\Block\Widget\Context;
use Magento\Framework\Exception\NoSuchEntityException as NoSuchEntityExceptionAlias;
use Magento\Store\Model\StoreManagerInterface;
use Mageplaza\OscUltimate\Block\Adminhtml\Layout\AbstractBlock;
use Mageplaza\OscUltimate\Helper\Data as OscHelper;

/**
 * Class Column
 * @package Mageplaza\OscUltimate\Block\Adminhtml\Layout\ManageBlock
 */
class Column extends AbstractBlock
{
    const BLOCK_ID = '1column';

    /**
     * @var string
     */
    protected $_template = 'Mageplaza_OscUltimate::block/manageBlock/column.phtml';

    /**
     * @var OscHelper
     */
    protected $helper;

    /**
     * @var string[]
     */
    protected $sortedFields = [
        'addressInformation',
        'shippingMethod',
        'paymentMethod',
        'orderSummary'
    ];

    /**
     * Column constructor.
     *
     * @param Context $context
     * @param OscHelper $helper
     * @param array $data
     */
    public function __construct(
        Context $context,
        OscHelper $helper,
        StoreManagerInterface $storeManager,
        array $data = []
    ) {
        parent::__construct($context, $helper, $storeManager, $data);
    }

    /**
     * @return string
     */
    public function getBlockId()
    {
        return static::BLOCK_ID;
    }

    /**
     * @return mixed|string[]
     * @throws NoSuchEntityExceptionAlias
     */
    public function getSortedFields()
    {
        $manageBlock = $this->getDataSortedFields();

        if ($manageBlock && $manageBlock['layout'] === static::BLOCK_ID) {
            return $manageBlock['data'];
        }

        return $this->sortedFields;
    }

}

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

namespace Mageplaza\RMA\Model\Config\Source\RMARequest;

use Magento\Framework\Option\ArrayInterface;
use Mageplaza\RMA\Model\ResourceModel\Status\CollectionFactory;

/**
 * Class Status
 * @package Mageplaza\RMA\Model\Config\Source\RMARequest
 */
class Status implements ArrayInterface
{
    /**
     * @var CollectionFactory
     */
    protected $_statusColFact;

    /**
     * Status constructor.
     *
     * @param CollectionFactory $statusColFact
     */
    public function __construct(CollectionFactory $statusColFact)
    {
        $this->_statusColFact = $statusColFact;
    }

    /**
     * @return array
     */
    public function toOptionArray()
    {
        $options = [];
        $statusCol = $this->_statusColFact->create()->addFieldToFilter('is_active', '1');

        foreach ($statusCol as $status) {
            $options[] = [
                'value' => $status->getId(),
                'label' => $status->getLabel(),
                'allow' => $status->getAllowAction()
            ];
        }

        return $options;
    }
}

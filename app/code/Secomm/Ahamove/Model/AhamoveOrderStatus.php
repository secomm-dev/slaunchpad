<?php
/**
 * @author Secomm SCS Team
 * @copyright Copyright (c) 2023. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
namespace Secomm\Ahamove\Model;

use Magento\Framework\Model\AbstractModel;
use Secomm\Ahamove\Model\ResourceModel\AhamoveOrderStatus as ResourceModel;

class AhamoveOrderStatus extends AbstractModel
{
    /**
     * Ahamove Shipping states
     */
    public const STATE_IDLE = 'IDLE';

    public const STATE_ASSIGNING = 'ASSIGNING';

    public const STATE_IN_PROCESS = 'IN PROCESS';

    public const STATE_COMPLETED = 'COMPLETED';

    public const STATE_CANCELLED = 'CANCELLED';

    /**
     * Ahamove Shipping Sub statuses
     */
    public const SUB_STATUS_IN_RETURN = 'IN_RETURN';
    public const SUB_STATUS_RETURNED = 'RETURNED';
    public const SUB_STATUS_FAILED = 'FAILED';

    /**
     * @var string
     */
    protected $_eventPrefix = 'ahamove_order_status_model';
    protected $order;
    protected $orderRepository;
    protected $package;

    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        $this->orderRepository = $orderRepository;
        parent::__construct(
            $context,
            $registry,
            $resource,
            $resourceCollection,
            $data
        );
    }

    /**
     * Initialize magento model.
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(ResourceModel::class);
    }
}

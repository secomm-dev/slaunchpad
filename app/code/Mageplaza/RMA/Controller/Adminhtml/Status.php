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

namespace Mageplaza\RMA\Controller\Adminhtml;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Registry;
use Mageplaza\RMA\Helper\Data as HelperData;
use Mageplaza\RMA\Model\ResourceModel\Status as StatusResource;
use Mageplaza\RMA\Model\StatusFactory;

/**
 * Class Status
 * @package Mageplaza\RMA\Controller\Adminhtml
 */
abstract class Status extends Action
{
    /** Authorization level of a basic admin session */
    const ADMIN_RESOURCE = 'Mageplaza_RMA::status';

    /**
     * Status model factory
     *
     * @var StatusFactory
     */
    public $statusFactory;

    /**
     * @var Registry
     */
    public $coreRegistry;

    /**
     * @var StatusResource
     */
    protected $_statusResource;

    /**
     * @var HelperData
     */
    protected $helperData;

    /**
     * Status constructor.
     *
     * @param Context $context
     * @param Registry $coreRegistry
     * @param StatusFactory $statusFactory
     * @param StatusResource $statusResource
     * @param HelperData $helperData
     */
    public function __construct(
        Context $context,
        Registry $coreRegistry,
        StatusFactory $statusFactory,
        StatusResource $statusResource,
        HelperData $helperData
    ) {
        $this->coreRegistry = $coreRegistry;
        $this->statusFactory = $statusFactory;
        $this->_statusResource = $statusResource;
        $this->helperData = $helperData;

        parent::__construct($context);
    }

    /**
     * @param bool $register
     *
     * @return bool|\Mageplaza\RMA\Model\Status
     */
    protected function initStatus($register = false)
    {
        $statusId = (int)$this->getRequest()->getParam('id');

        /** @var \Mageplaza\RMA\Model\Status $status */
        $status = $this->statusFactory->create();

        if ($statusId) {
            $this->_statusResource->load($status, $statusId);
            if (!$status->getId()) {
                $this->messageManager->addErrorMessage(__('This status no longer exists.'));

                return false;
            }
        }

        if ($register) {
            $this->coreRegistry->register('mageplaza_rma_status', $status);
        }

        return $status;
    }
}

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
use Mageplaza\RMA\Model\ResourceModel\ShippingLabel as ShippingLabelResource;
use Mageplaza\RMA\Model\ShippingLabelFactory;

/**
 * Class ShippingLabel
 * @package Mageplaza\RMA\Controller\Adminhtml
 */
abstract class ShippingLabel extends Action
{
    /** Authorization level of a basic admin session */
    const ADMIN_RESOURCE = 'Mageplaza_RMA::shipping_label';

    /**
     * ShippingLabel model factory
     *
     * @var ShippingLabelFactory
     */
    public $shippingLabelFactory;

    /**
     * @var Registry
     */
    public $coreRegistry;

    /**
     * @var ShippingLabelResource
     */
    protected $_shippingLabelResource;

    /**
     * ShippingLabel constructor.
     *
     * @param Context $context
     * @param Registry $coreRegistry
     * @param ShippingLabelFactory $shippingLabelFactory
     * @param ShippingLabelResource $shippingLabelResource
     */
    public function __construct(
        Context $context,
        Registry $coreRegistry,
        ShippingLabelFactory $shippingLabelFactory,
        ShippingLabelResource $shippingLabelResource
    ) {
        $this->coreRegistry = $coreRegistry;
        $this->shippingLabelFactory = $shippingLabelFactory;
        $this->_shippingLabelResource = $shippingLabelResource;

        parent::__construct($context);
    }

    /**
     * @param bool $register
     *
     * @return bool|\Mageplaza\RMA\Model\ShippingLabel
     */
    protected function initShippingLabel($register = false)
    {
        $shippingLabelId = (int)$this->getRequest()->getParam('id');

        /** @var \Mageplaza\RMA\Model\ShippingLabel $shippingLabel */
        $shippingLabel = $this->shippingLabelFactory->create();

        if ($shippingLabelId) {
            $this->_shippingLabelResource->load($shippingLabel, $shippingLabelId);
            if (!$shippingLabel->getId()) {
                $this->messageManager->addErrorMessage(__('This shipping label no longer exists.'));

                return false;
            }
        }
        if ($register) {
            $this->coreRegistry->register('mageplaza_rma_shipping_label', $shippingLabel);
        }

        return $shippingLabel;
    }
}

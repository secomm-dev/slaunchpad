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

namespace Mageplaza\RMA\Controller\Adminhtml\ShippingLabel;

use Exception;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\Redirect;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Ui\Component\MassAction\Filter;
use Mageplaza\RMA\Model\ResourceModel\ShippingLabel as ShippingLabelResource;
use Mageplaza\RMA\Model\ResourceModel\ShippingLabel\Collection;
use Mageplaza\RMA\Model\ResourceModel\ShippingLabel\CollectionFactory;
use Mageplaza\RMA\Model\ShippingLabel;

/**
 * Class MassStatus
 * @package Mageplaza\RMA\Controller\Adminhtml\ShippingLabel
 */
class MassStatus extends Action
{
    /** Authorization level of a basic admin session */
    const ADMIN_RESOURCE = 'Mageplaza_RMA::shipping_label';

    /**
     * @var Filter
     */
    public $filter;

    /**
     * @var CollectionFactory
     */
    public $collectionFactory;

    /**
     * @var ShippingLabelResource
     */
    protected $_shippingLabelResource;

    /**
     * MassStatus constructor.
     *
     * @param Context $context
     * @param Filter $filter
     * @param CollectionFactory $collectionFactory
     * @param ShippingLabelResource $shippingLabelResource
     */
    public function __construct(
        Context $context,
        Filter $filter,
        CollectionFactory $collectionFactory,
        ShippingLabelResource $shippingLabelResource
    ) {
        $this->filter = $filter;
        $this->collectionFactory = $collectionFactory;
        $this->_shippingLabelResource = $shippingLabelResource;

        parent::__construct($context);
    }

    /**
     * @return Redirect|ResponseInterface|ResultInterface
     * @throws LocalizedException
     */
    public function execute()
    {
        /** @var Collection $collection */
        $collection = $this->filter->getCollection($this->collectionFactory->create());
        $status = (int)$this->getRequest()->getParam('status');

        $statusUpdated = 0;
        foreach ($collection as $shippingLabel) {
            /** @var ShippingLabel $shippingLabel */
            try {
                $shippingLabel->setStatus($status);
                $this->_shippingLabelResource->save($shippingLabel);
                $statusUpdated++;
            } catch (LocalizedException $e) {
                $this->messageManager->addErrorMessage($e->getMessage());
            } catch (Exception $e) {
                $this->messageManager->addExceptionMessage(
                    $e,
                    __('Something went wrong while updating status for %1.', $shippingLabel->getLabel())
                );
            }
        }

        if ($statusUpdated) {
            $this->messageManager->addSuccessMessage(__('A total of %1 record(s) have been updated.', $statusUpdated));
        }

        /** @var Redirect $resultRedirect */
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

        return $resultRedirect->setPath('*/*/');
    }
}

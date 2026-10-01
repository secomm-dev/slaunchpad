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

namespace Mageplaza\RMA\Controller\Adminhtml\Status;

use Exception;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\Redirect;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Ui\Component\MassAction\Filter;
use Mageplaza\RMA\Helper\Data as HelperData;
use Mageplaza\RMA\Model\ResourceModel\Status as StatusResource;
use Mageplaza\RMA\Model\ResourceModel\Status\Collection;
use Mageplaza\RMA\Model\ResourceModel\Status\CollectionFactory;
use Mageplaza\RMA\Model\Status;

/**
 * Class MassActive
 * @package Mageplaza\RMA\Controller\Adminhtml\Status
 */
class MassActive extends Action
{
    /** Authorization level of a basic admin session */
    const ADMIN_RESOURCE = 'Mageplaza_RMA::status';

    /**
     * @var Filter
     */
    public $filter;

    /**
     * @var CollectionFactory
     */
    public $collectionFactory;

    /**
     * @var StatusResource
     */
    protected $_statusResource;

    /**
     * @var HelperData
     */
    protected $helperData;

    /**
     * MassActive constructor.
     *
     * @param Context $context
     * @param Filter $filter
     * @param CollectionFactory $collectionFactory
     * @param StatusResource $statusResource
     * @param HelperData $helperData
     */
    public function __construct(
        Context $context,
        Filter $filter,
        CollectionFactory $collectionFactory,
        StatusResource $statusResource,
        HelperData $helperData
    ) {
        $this->filter = $filter;
        $this->collectionFactory = $collectionFactory;
        $this->_statusResource = $statusResource;
        $this->helperData = $helperData;

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
        $isActive = (int)$this->getRequest()->getParam('status');

        $statusUpdated = 0;

        foreach ($collection as $status) {
            /** @var Status $status */
            try {
                if (!$this->helperData->isDefaultStatus($status->getId())) {
                    $status->setIsActive($isActive);
                    $this->_statusResource->save($status);
                    $statusUpdated++;
                } else {
                    $this->messageManager->addErrorMessage(
                        __('You can not disable the default status! %1.', $status->getName())
                    );
                }
            } catch (Exception $e) {
                $this->messageManager->addExceptionMessage(
                    $e,
                    __('Something went wrong while updating active for %1.', $status->getName())
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

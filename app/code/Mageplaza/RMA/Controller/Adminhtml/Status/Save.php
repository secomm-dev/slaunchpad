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
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Registry;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Mageplaza\RMA\Controller\Adminhtml\Status;
use Mageplaza\RMA\Helper\Data as HelperData;
use Mageplaza\RMA\Model\ResourceModel\Status as StatusResource;
use Mageplaza\RMA\Model\StatusFactory;
use RuntimeException;

/**
 * Class Save
 * @package Mageplaza\RMA\Controller\Adminhtml\Status
 */
class Save extends Status
{
    /**
     * @var DateTime
     */
    public $date;

    /**
     * Save constructor.
     *
     * @param Context $context
     * @param Registry $coreRegistry
     * @param DateTime $date
     * @param StatusFactory $statusFactory
     * @param StatusResource $statusResource
     * @param HelperData $helperData
     */
    public function __construct(
        Context $context,
        Registry $coreRegistry,
        DateTime $date,
        StatusFactory $statusFactory,
        StatusResource $statusResource,
        HelperData $helperData
    ) {
        $this->date = $date;

        parent::__construct(
            $context,
            $coreRegistry,
            $statusFactory,
            $statusResource,
            $helperData
        );
    }

    /**
     * @return ResponseInterface|Redirect|ResultInterface
     */
    public function execute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();

        if ($data = $this->getRequest()->getPost('status')) {
            /** @var \Mageplaza\RMA\Model\Status $status */
            $status = $this->initStatus();
            $this->prepareData($status, $data);

            $this->_eventManager->dispatch('mageplaza_rma_status_prepare_save', [
                'status' => $status,
                'request' => $this->getRequest()
            ]);

            if (!$status->getIsActive() && $this->helperData->isDefaultStatus($status->getId())) {
                $this->messageManager->addErrorMessage(__('You can not disable the default status!'));
                $resultRedirect->setPath('*/*/edit', ['id' => $status->getId(), '_current' => true]);

                return $resultRedirect;
            }

            try {
                $this->_statusResource->save($status);
                $this->messageManager->addSuccessMessage(__('The status has been saved.'));
                $this->_getSession()->setData('mageplaza_rma_status_data', false);

                if ($this->getRequest()->getParam('back')) {
                    $resultRedirect->setPath('*/*/edit', ['id' => $status->getId(), '_current' => true]);
                } else {
                    $resultRedirect->setPath('*/*/');
                }

                return $resultRedirect;
            } catch (LocalizedException $e) {
                $this->messageManager->addErrorMessage($e->getMessage());
            } catch (RuntimeException $e) {
                $this->messageManager->addErrorMessage($e->getMessage());
            } catch (Exception $e) {
                $this->messageManager->addExceptionMessage($e, __('Something went wrong while saving the Status.'));
            }

            $this->_getSession()->setData('mageplaza_rma_status_data', $data);

            $resultRedirect->setPath('*/*/edit', ['id' => $status->getId(), '_current' => true]);

            return $resultRedirect;
        }

        $resultRedirect->setPath('*/*/');

        return $resultRedirect;
    }

    /**
     * @param \Mageplaza\RMA\Model\Status $status
     * @param array $data
     *
     * @return $this
     */
    protected function prepareData($status, $data)
    {
        if ($status->getCreatedAt() === null) {
            $data['created_at'] = $this->date->date();
        }
        $data['updated_at'] = $this->date->date();

        $status->addData($data);

        return $this;
    }
}

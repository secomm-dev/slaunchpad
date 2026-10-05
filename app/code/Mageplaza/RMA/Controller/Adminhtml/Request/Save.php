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

namespace Mageplaza\RMA\Controller\Adminhtml\Request;

use Exception;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem\Directory\WriteInterface;
use Magento\Framework\Registry;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Mageplaza\RMA\Controller\Adminhtml\Request;
use Mageplaza\RMA\Helper\Data as HelperData;
use Mageplaza\RMA\Helper\Image as HelperImage;
use Mageplaza\RMA\Model\RequestFactory;
use Mageplaza\RMA\Model\ResourceModel\Request as RequestResource;
use RuntimeException;

/**
 * Class Save
 * @package Mageplaza\RMA\Controller\Adminhtml\Request
 */
class Save extends Request
{
    /**
     * @var DateTime
     */
    public $date;

    /**
     * @var WriteInterface
     */
    protected $_mediaDirectory;

    /**
     * @var HelperImage
     */
    protected $_helperImage;

    /**
     * Save constructor.
     *
     * @param Context $context
     * @param Registry $coreRegistry
     * @param DateTime $date
     * @param RequestFactory $requestFactory
     * @param RequestResource $requestResource
     * @param HelperImage $helperImage
     */
    public function __construct(
        Context $context,
        Registry $coreRegistry,
        DateTime $date,
        RequestFactory $requestFactory,
        RequestResource $requestResource,
        HelperImage $helperImage
    ) {
        $this->date = $date;
        $this->_helperImage = $helperImage;
        $this->_mediaDirectory = $helperImage->getMediaDirectory();

        parent::__construct(
            $context,
            $coreRegistry,
            $requestFactory,
            $requestResource
        );
    }

    /**
     * @return ResponseInterface|Redirect|ResultInterface
     * @throws LocalizedException
     */
    public function execute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        if ($data = $this->getRequest()->getPost('request')) {
            unset($data['request_id']);
            if ($dataFile = $this->getRequest()->getPost('files')) {
                $data['files'] = $dataFile;
            }
            /** Upload files */
            if (isset($data['files']) && count($data['files'])) {
                $data['files'] = HelperData::jsonEncode($this->_helperImage->processImagesGallery($data['files']));
            }
            /** @var \Mageplaza\RMA\Model\Request $request */
            $request = $this->initRequest();
            $this->prepareData($request, $data);

            $this->_eventManager->dispatch('mageplaza_rma_request_prepare_save', [
                'rma_request' => $request,
                'request' => $this->getRequest()
            ]);

            try {
                $this->_requestResource->save($request);
                $this->messageManager->addSuccessMessage(__('The request has been saved.'));
                $this->_getSession()->setData('mageplaza_rma_request_data', false);

                if ($this->getRequest()->getParam('back')) {
                    $resultRedirect->setPath('*/*/edit', ['id' => $request->getId(), '_current' => true]);
                } else {
                    $resultRedirect->setPath('*/*/');
                }

                return $resultRedirect;
            } catch (LocalizedException $e) {
                $this->messageManager->addErrorMessage($e->getMessage());
            } catch (RuntimeException $e) {
                $this->messageManager->addErrorMessage($e->getMessage());
            } catch (Exception $e) {
                $this->messageManager->addExceptionMessage($e, __('Something went wrong while saving the Request.'));
            }

            $this->_getSession()->setData('mageplaza_rma_request_data', $data);

            $resultRedirect->setPath('*/*/edit', ['id' => $request->getId(), '_current' => true]);

            return $resultRedirect;
        }

        $resultRedirect->setPath('*/*/');

        return $resultRedirect;
    }

    /**
     * @param \Mageplaza\RMA\Model\Request $request
     * @param array $data
     *
     * @return $this
     */
    protected function prepareData($request, $data)
    {
        if ($request->getCreatedAt() === null) {
            $data['created_at'] = $this->date->date();
        }
        $data['updated_at'] = $this->date->date();
        if (isset($data['products'])) {
            $data['products'] = HelperData::jsonEncode($data['products']);
        }
        $request->addData($data);

        return $this;
    }
}

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
 * @package     Mageplaza_Lookbook
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\Lookbook\Controller\Adminhtml\Lookbook;

use Exception;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\ForwardFactory;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Registry;
use Magento\Framework\View\Result\PageFactory;
use Mageplaza\Lookbook\Controller\Adminhtml\Lookbook;
use Mageplaza\Lookbook\Helper\Media;
use Mageplaza\Lookbook\Model\LookbookFactory;
use Mageplaza\Lookbook\Model\ResourceModel\Lookbook as ResourceModel;
use RuntimeException;

/**
 * Class Save
 * @package Mageplaza\Lookbook\Controller\Adminhtml\Lookbook
 */
class Save extends Lookbook
{
    /**
     * @var Media
     */
    protected $media;

    /**
     * Save constructor.
     *
     * @param Context $context
     * @param Registry $coreRegistry
     * @param PageFactory $resultPageFactory
     * @param ForwardFactory $resultForwardFactory
     * @param LookbookFactory $lookbookFactory
     * @param ResourceModel $resourceModel
     * @param Media $media
     */
    public function __construct(
        Context $context,
        Registry $coreRegistry,
        PageFactory $resultPageFactory,
        ForwardFactory $resultForwardFactory,
        LookbookFactory $lookbookFactory,
        ResourceModel $resourceModel,
        Media $media
    ) {
        $this->media = $media;
        parent::__construct(
            $context,
            $coreRegistry,
            $resultPageFactory,
            $resultForwardFactory,
            $lookbookFactory,
            $resourceModel
        );
    }

    /**
     * @return ResponseInterface|ResultInterface|void
     */
    public function execute()
    {
        $data = $this->getRequest()->getPostValue();
        if (!$data) {
            $this->_redirect('*/*/');
        }

        if ($data['marker']) {
            $data['marker'] = str_replace('\n','\\\n',$data['marker']);
        }

        $id    = $this->getRequest()->getParam('lookbook_id');
        $model = $this->_initLookbook();
        $this->media->uploadImage($data, 'image', '', $model->getImage());

        if (!isset($data['image']) || empty($data['image'])) {
            $this->messageManager->addErrorMessage(__('Please upload image file. Allowed file types: jpg, gif, png.'));
            $this->_session->setPageData($data);
            if ($id) {
                $this->_redirect('*/*/edit', ['lookbook_id' => $model->getId()]);
            } else {
                $this->_redirect('*/*/new');
            }

            return;
        }

        $model->addData($data);
        $this->_session->setPageData($data);
        $this->_eventManager->dispatch(
            'mplookbook_lookbook_prepare_save',
            [
                'lookbook' => $model,
                'request'  => $this->getRequest()
            ]
        );

        try {
            $this->resourceModel->save($model);
            $this->messageManager->addSuccessMessage(__('The Lookbook has been saved.'));
            $this->_session->setPageData(false);

            if ($this->getRequest()->getParam('back')) {
                $this->_redirect('*/*/edit', ['lookbook_id' => $model->getId()]);

                return;
            }
            $this->_redirect('*/*/');
        } catch (RuntimeException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (Exception $e) {
            $this->messageManager->addErrorMessage($e, __('Something went wrong while saving the Lookbook.'));
            $this->_session->setPageData($data);
            if ($id) {
                $this->_redirect('*/*/edit', ['lookbook_id' => $model->getId()]);
            } else {
                $this->_redirect('*/*/new');
            }

            return;
        }
    }
}

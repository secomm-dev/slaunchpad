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

namespace Mageplaza\Lookbook\Controller\Adminhtml\Slider;

use Exception;
use Mageplaza\Lookbook\Controller\Adminhtml\Slider;
use RuntimeException;

/**
 * Class Save
 * @package Mageplaza\Lookbook\Controller\Adminhtml\Slider
 */
class Save extends Slider
{

    /**
     * @inheritDoc
     */
    public function execute()
    {
        $data = $this->getRequest()->getPostValue();

        if (!$data) {
            $this->_redirect('*/*/');
        }

        $id = $this->getRequest()->getParam('slider_id');
        $model = $this->initModel();
        if (empty($data['slider_id'])) {
            $data['slider_id'] = null;
        }

        $model->addData($data);
        $this->_session->setPageData($data);
        $this->_eventManager->dispatch(
            'mplookbook_slider_prepare_save',
            [
                'slider' => $model,
                'request' => $this->getRequest()
            ]
        );

        try {
            $this->resourceModel->save($model);
            $this->messageManager->addSuccessMessage(__('The Slider has been saved.'));
            $this->_session->setPageData(false);

            if ($this->getRequest()->getParam('back')) {
                $this->_redirect('*/*/edit', ['slider_id' => $model->getId()]);

                return;
            }
            $this->_redirect('*/*/');
        } catch (RuntimeException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (Exception $e) {
            $this->messageManager->addErrorMessage($e, __('Something went wrong while saving the Slider.'));
            $this->_session->setPageData($data);
            if ($id) {
                $this->_redirect('*/*/edit', ['slider_id' => $model->getId()]);
            } else {
                $this->_redirect('*/*/new');
            }

            return;
        }
    }
}

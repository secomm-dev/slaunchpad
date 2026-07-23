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

use Magento\Backend\Model\View\Result\Page;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Mageplaza\Lookbook\Controller\Adminhtml\Slider;

/**
 * Class Edit
 * @package Mageplaza\Lookbook\Controller\Adminhtml\Slider
 */
class Edit extends Slider
{
    /**
     * Authorization level of a basic admin session
     *
     * @see _isAllowed()
     */
    const ADMIN_RESOURCE = 'Mageplaza_Lookbook::slideredit';

    /**
     * @return Page|ResponseInterface|ResultInterface
     */
    public function execute()
    {
        $id = (int)$this->getRequest()->getParam('slider_id');
        $model = $this->initModel();

        // set entered data if was error when we do save
        $data = $this->_session->getData('mplookbook_slider_data', true);
        if (!empty($data)) {
            $model->setData($data);
        }

        $this->coreRegistry->register('mplookbook_slider', $model);

        // 5. Build edit form
        /** @var Page $resultPage */
        $resultPage = $this->_initPage();
        $resultPage->addBreadcrumb(
            $id ? __('Edit Slider') : __('New Slider'),
            $id ? __('Edit Slider') : __('New Slider')
        );
        $resultPage->getConfig()->getTitle()->prepend(__('Slider'));
        $resultPage->getConfig()->getTitle()->prepend($id ? $model->getName() : __('New Slider'));

        return $resultPage;
    }
}

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

namespace Mageplaza\RMA\Controller\Adminhtml\Request\Template;

use Exception;
use Magento\Framework\App\Request\Http;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Forward;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\View\Element\Messages;
use Mageplaza\RMA\Controller\Adminhtml\Request\Template;

/**
 * Class Delete
 * @package Mageplaza\RMA\Controller\Adminhtml\Request\Template
 */
class Delete extends Template
{
    /**
     * @return ResponseInterface|Forward|Json|ResultInterface
     */
    public function execute()
    {
        /** @var Http $request */
        $request = $this->getRequest();
        if (!$request->isAjax()) {
            return $this->_resultFwFactory->create()->forward('noroute');
        }
        /** @var Messages $messageBlock */
        $messageBlock = $this->_layout->createBlock(Messages::class);
        if (!$template = $this->initTemplate()) {
            $messageBlock->addError(__('This template not found.'));
            $result = [
                'status' => false,
                'message' => $messageBlock->toHtml()
            ];

            return $this->_resultJson->setData($result);
        }
        /** @var \Mageplaza\RMA\Model\Template $template */
        try {
            $this->_templateResource->delete($template);
            $messageBlock->addSuccess(__('The Template has been deleted.'));
            $result['status'] = true;
        } catch (Exception $e) {
            $messageBlock->addError(__($e->getMessage()));
            $result['status'] = false;
        }
        $result['message'] = $messageBlock->toHtml();

        return $this->_resultJson->setData($result);
    }
}

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
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Request\Http;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\ForwardFactory;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Registry;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\View\Element\Messages;
use Magento\Framework\View\Layout;
use Mageplaza\RMA\Controller\Adminhtml\Request\Template;
use Mageplaza\RMA\Model\ResourceModel\Template as TemplateResource;
use Mageplaza\RMA\Model\TemplateFactory;

/**
 * Class Save
 * @package Mageplaza\RMA\Controller\Adminhtml\Request\Template
 */
class Save extends Template
{
    /**
     * @var DateTime
     */
    protected $_dateTime;

    /**
     * Save constructor.
     *
     * @param Context $context
     * @param Registry $coreRegistry
     * @param TemplateFactory $templateFactory
     * @param TemplateResource $templateResource
     * @param Layout $layout
     * @param Json $resultJson
     * @param ForwardFactory $resultForwardFactory
     * @param DateTime $dateTime
     */
    public function __construct(
        Context $context,
        Registry $coreRegistry,
        TemplateFactory $templateFactory,
        TemplateResource $templateResource,
        Layout $layout,
        Json $resultJson,
        ForwardFactory $resultForwardFactory,
        DateTime $dateTime
    ) {
        $this->_dateTime = $dateTime;

        parent::__construct(
            $context,
            $coreRegistry,
            $layout,
            $resultJson,
            $resultForwardFactory,
            $templateFactory,
            $templateResource
        );
    }

    /**
     * @return ResponseInterface|Json|ResultInterface
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
        if (!$data = $request->getPost('template')) {
            $messageBlock->addError(__('This template data not found.'));
            $result = [
                'status' => false,
                'message' => $messageBlock->toHtml()
            ];

            return $this->_resultJson->setData($result);
        }
        $data = $request->getPost('template');
        /** @var \Mageplaza\RMA\Model\Template $template */
        $template = $this->initTemplate();
        $this->prepareData($template, $data);
        $this->_eventManager->dispatch(
            'mageplaza_rma_template_prepare_save',
            ['template' => $template, 'request' => $this->getRequest()]
        );
        try {
            $this->_templateResource->save($template);
            $messageBlock->addSuccess(__('You have saved the Template'));
            $result['status'] = true;
        } catch (Exception $e) {
            $messageBlock->addError(__($e->getMessage()));
            $result['status'] = false;
        }
        $result['message'] = $messageBlock->toHtml();

        return $this->_resultJson->setData($result);
    }

    /**
     * @param \Mageplaza\RMA\Model\Template $template
     * @param array $data
     *
     * @return $this
     */
    protected function prepareData($template, $data)
    {
        if ($template->getCreatedAt() === null) {
            $data['created_at'] = $this->_dateTime->date();
        }
        $data['updated_at'] = $this->_dateTime->date();
        $template->addData($data);

        return $this;
    }
}

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

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\ForwardFactory;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Registry;
use Magento\Framework\View\Layout;
use Mageplaza\RMA\Model\ResourceModel\Template as TemplateResource;
use Mageplaza\RMA\Model\TemplateFactory;

/**
 * Class Template
 * @package Mageplaza\RMA\Controller\Adminhtml\Request
 */
abstract class Template extends Action
{
    /** Authorization level of a basic admin session */
    const ADMIN_RESOURCE = 'Mageplaza_RMA::request';

    /**
     * @var Registry
     */
    protected $_coreRegistry;

    /**
     * @var Layout
     */
    protected $_layout;

    /**
     * @var Json
     */
    protected $_resultJson;

    /**
     * @var ForwardFactory
     */
    protected $_resultFwFactory;

    /**
     * @var TemplateFactory
     */
    protected $_templateFactory;

    /**
     * @var TemplateResource
     */
    protected $_templateResource;

    /**
     * Template constructor.
     *
     * @param Context $context
     * @param Registry $coreRegistry
     * @param Layout $layout
     * @param Json $resultJson
     * @param ForwardFactory $resultForwardFactory
     * @param TemplateFactory $templateFactory
     * @param TemplateResource $templateResource
     */
    public function __construct(
        Context $context,
        Registry $coreRegistry,
        Layout $layout,
        Json $resultJson,
        ForwardFactory $resultForwardFactory,
        TemplateFactory $templateFactory,
        TemplateResource $templateResource
    ) {
        $this->_coreRegistry = $coreRegistry;
        $this->_layout = $layout;
        $this->_resultJson = $resultJson;
        $this->_resultFwFactory = $resultForwardFactory;
        $this->_templateFactory = $templateFactory;
        $this->_templateResource = $templateResource;

        parent::__construct($context);
    }

    /**
     * @param bool $register
     *
     * @return bool|\Mageplaza\RMA\Model\Template
     */
    protected function initTemplate($register = false)
    {
        $templateId = (int)$this->getRequest()->getParam('template_id');

        /** @var \Mageplaza\RMA\Model\Template $template */
        $template = $this->_templateFactory->create();
        if ($templateId) {
            $this->_templateResource->load($template, $templateId);
            if (!$template->getId()) {
                $this->messageManager->addErrorMessage(__('This template no longer exists.'));

                return false;
            }
        }

        if ($register) {
            $this->_coreRegistry->register('mageplaza_rma_template', $template);
        }

        return $template;
    }
}

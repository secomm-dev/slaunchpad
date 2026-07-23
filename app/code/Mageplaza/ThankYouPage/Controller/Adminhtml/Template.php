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
 * @package     Mageplaza_ThankYouPage
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\ThankYouPage\Controller\Adminhtml;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\ForwardFactory;
use Magento\Backend\Model\View\Result\Page;
use Magento\Framework\Registry;
use Magento\Framework\Stdlib\DateTime\Filter\Date;
use Magento\Framework\View\Result\PageFactory;
use Mageplaza\ThankYouPage\Helper\Data;
use Mageplaza\ThankYouPage\Model\TemplateFactory;
use Psr\Log\LoggerInterface;

/**
 * Class Template
 * @package Mageplaza\ThankYouPage\Controller\Adminhtml
 */
abstract class Template extends Action
{
    /**
     * @var ForwardFactory
     */
    protected $resultForwardFactory;

    /**
     * @var PageFactory
     */
    protected $resultPageFactory;

    /**
     * Core registry
     *
     * @var Registry
     */
    protected $coreRegistry = null;

    /**
     * @var Date
     */
    protected $_dateFilter;

    /**
     * @var Data
     */
    protected $helperData;

    /**
     * @var TemplateFactory
     */
    protected $templateFactory;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Template constructor.
     *
     * @param Context $context
     * @param ForwardFactory $resultForwardFactory
     * @param PageFactory $resultPageFactory
     * @param Date $dateFilter
     * @param Registry $coreRegistry
     * @param Data $helperData
     * @param TemplateFactory $templateFactory
     * @param LoggerInterface $logger
     */
    public function __construct(
        Context $context,
        ForwardFactory $resultForwardFactory,
        PageFactory $resultPageFactory,
        Date $dateFilter,
        Registry $coreRegistry,
        Data $helperData,
        TemplateFactory $templateFactory,
        LoggerInterface $logger
    ) {
        $this->resultForwardFactory = $resultForwardFactory;
        $this->resultPageFactory    = $resultPageFactory;
        $this->coreRegistry         = $coreRegistry;
        $this->_dateFilter          = $dateFilter;
        $this->helperData           = $helperData;
        $this->templateFactory      = $templateFactory;
        $this->logger               = $logger;

        parent::__construct($context);
    }

    /**
     * Init layout, menu and breadcrumb
     *
     * @return Page
     */
    protected function _initAction()
    {
        /** @var Page $resultPage */
        $resultPage = $this->resultPageFactory->create();

        return $resultPage;
    }
}

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

namespace Mageplaza\RMA\Controller\Adminhtml\Rule;

use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\Page;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Registry;
use Magento\Framework\View\Result\Page as ResultPage;
use Magento\Framework\View\Result\PageFactory;
use Mageplaza\RMA\Controller\Adminhtml\Rule;
use Mageplaza\RMA\Model\ResourceModel\Rule as RuleResource;
use Mageplaza\RMA\Model\RuleFactory;

/**
 * Class Edit
 * @package Mageplaza\RMA\Controller\Adminhtml\Rule
 */
class Edit extends Rule
{
    /**
     * Page factory
     *
     * @var PageFactory
     */
    public $resultPageFactory;

    /**
     * Edit constructor.
     *
     * @param Context $context
     * @param Registry $coreRegistry
     * @param PageFactory $resultPageFactory
     * @param RuleFactory $ruleFactory
     * @param RuleResource $ruleResource
     */
    public function __construct(
        Context $context,
        Registry $coreRegistry,
        PageFactory $resultPageFactory,
        RuleFactory $ruleFactory,
        RuleResource $ruleResource
    ) {
        $this->resultPageFactory = $resultPageFactory;

        parent::__construct(
            $context,
            $coreRegistry,
            $ruleFactory,
            $ruleResource
        );
    }

    /**
     * @return Page|ResponseInterface|Redirect|ResultInterface|ResultPage
     */
    public function execute()
    {
        /** @var \Mageplaza\RMA\Model\Rule $rule */
        $rule = $this->initRule();
        if (!$rule) {
            $resultRedirect = $this->resultRedirectFactory->create();
            $resultRedirect->setPath('*/*/');

            return $resultRedirect;
        }

        $data = $this->_session->getData('mageplaza_rma_rule_data', true);
        if (!empty($data)) {
            $rule->setData($data);
        }

        $this->coreRegistry->register('mageplaza_rma_rule', $rule);

        /** @var Page|ResultPage $resultPage */
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('Mageplaza_RMA::rule');
        $resultPage->getConfig()->getTitle()->set(__('Manage Rules'));

        $title = $rule->getId() ? $rule->getName() : __('New Rule');
        $resultPage->getConfig()->getTitle()->prepend($title);

        return $resultPage;
    }
}

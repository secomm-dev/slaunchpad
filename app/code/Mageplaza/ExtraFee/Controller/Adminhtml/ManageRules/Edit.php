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
 * @package     Mageplaza_ExtraFee
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\ExtraFee\Controller\Adminhtml\ManageRules;

use Magento\Backend\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Registry;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;
use Mageplaza\ExtraFee\Controller\Adminhtml\AbstractManageRules;
use Mageplaza\ExtraFee\Model\Rule;
use Mageplaza\ExtraFee\Model\RuleFactory;

/**
 * Class Edit
 * @package Mageplaza\ExtraFee\Controller\Adminhtml\ManageRules
 */
class Edit extends AbstractManageRules
{
    /**
     * Page factory
     *
     * @var PageFactory
     */
    protected $resultPageFactory;

    /**
     * Edit constructor.
     *
     * @param RuleFactory $ruleFactory
     * @param Registry $coreRegistry
     * @param Context $context
     * @param PageFactory $resultPageFactory
     */
    public function __construct(
        RuleFactory $ruleFactory,
        Registry $coreRegistry,
        Context $context,
        PageFactory $resultPageFactory
    ) {
        $this->resultPageFactory = $resultPageFactory;

        parent::__construct($ruleFactory, $coreRegistry, $context);
    }

    /**
     * @return \Magento\Backend\Model\View\Result\Page|ResponseInterface|Redirect|ResultInterface|Page
     */
    public function execute()
    {
        /** @var Rule $rule */
        $rule = $this->initRule();
        if (!$rule) {
            $resultRedirect = $this->resultRedirectFactory->create();
            $resultRedirect->setPath('mpextrafee/managerules/index');

            return $resultRedirect;
        }

        $data = $this->_session->getData('mageplaza_extrafee_rule', true);
        if (!empty($data)) {
            $rule->setData($data);
        }

        $this->coreRegistry->register('mageplaza_extrafee_rule', $rule);

        /** @var \Magento\Backend\Model\View\Result\Page|Page $resultPage */
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('Mageplaza_ExtraFee::manage_rules');
        $resultPage->getConfig()->getTitle()->set(__('Rule'));
        $title = $rule->getId() ? __('Edit %1 rule', $rule->getName()) : __('New Rule');
        $resultPage->getConfig()->getTitle()->prepend($title);

        return $resultPage;
    }
}

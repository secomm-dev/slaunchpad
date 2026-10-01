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

namespace Mageplaza\RMA\Controller\Adminhtml;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Registry;
use Mageplaza\RMA\Model\ResourceModel\Rule as RuleResource;
use Mageplaza\RMA\Model\RuleFactory;

/**
 * Class Rule
 * @package Mageplaza\RMA\Controller\Adminhtml
 */
abstract class Rule extends Action
{
    /** Authorization level of a basic admin session */
    const ADMIN_RESOURCE = 'Mageplaza_RMA::rule';

    /**
     * Rule model factory
     *
     * @var RuleFactory
     */
    public $ruleFactory;

    /**
     * @var Registry
     */
    public $coreRegistry;

    /**
     * @var RuleResource
     */
    protected $_ruleResource;

    /**
     * Rule constructor.
     *
     * @param Context $context
     * @param Registry $coreRegistry
     * @param RuleFactory $ruleFactory
     * @param RuleResource $ruleResource
     */
    public function __construct(
        Context $context,
        Registry $coreRegistry,
        RuleFactory $ruleFactory,
        RuleResource $ruleResource
    ) {
        $this->ruleFactory = $ruleFactory;
        $this->coreRegistry = $coreRegistry;
        $this->_ruleResource = $ruleResource;

        parent::__construct($context);
    }

    /**
     * @param bool $register
     *
     * @return bool|\Mageplaza\RMA\Model\Rule
     */
    protected function initRule($register = false)
    {
        $ruleId = (int)$this->getRequest()->getParam('id');

        /** @var \Mageplaza\RMA\Model\Rule $rule */
        $rule = $this->ruleFactory->create();

        if ($ruleId) {
            $this->_ruleResource->load($rule, $ruleId);
            if (!$rule->getId()) {
                $this->messageManager->addErrorMessage(__('This rule no longer exists.'));

                return false;
            }
        }
        if ($register) {
            $this->coreRegistry->register('mageplaza_rma_rule', $rule);
        }

        return $rule;
    }
}

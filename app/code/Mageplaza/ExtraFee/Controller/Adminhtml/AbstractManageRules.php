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

namespace Mageplaza\ExtraFee\Controller\Adminhtml;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Registry;
use Mageplaza\ExtraFee\Model\Rule;
use Mageplaza\ExtraFee\Model\RuleFactory;

/**
 * Class AbstractManageRules
 * @package Mageplaza\ExtraFee\Controller\Adminhtml
 */
abstract class AbstractManageRules extends Action
{
    /**
     * Authorization level of a basic admin session
     *
     * @see _isAllowed()
     */
    const ADMIN_RESOURCE = 'Mageplaza_ExtraFee::manage_rules';

    /**
     * Rule model factory
     *
     * @var RuleFactory
     */
    protected $ruleFactory;

    /**
     * Core registry
     *
     * @var Registry
     */
    protected $coreRegistry;

    /**
     * AbstractManageProfiles constructor.
     *
     * @param RuleFactory $ruleFactory
     * @param Registry $coreRegistry
     * @param Context $context
     */
    public function __construct(
        RuleFactory $ruleFactory,
        Registry $coreRegistry,
        Context $context
    ) {
        $this->ruleFactory  = $ruleFactory;
        $this->coreRegistry = $coreRegistry;

        parent::__construct($context);
    }

    /**
     * @param bool $register
     *
     * @return bool|Rule
     */
    protected function initRule($register = false)
    {
        $ruleId = $this->getRequest()->getParam('rule_id');
        /** @var Rule $rule */
        $rule = $this->ruleFactory->create();

        if ($ruleId) {
            $rule = $rule->load($ruleId);
            if (!$rule->getId()) {
                $this->messageManager->addErrorMessage(__('The profile no longer exists.'));

                return false;
            }
        }
        if ($register) {
            $this->coreRegistry->register('mageplaza_extrafee_rule', $rule);
        }

        return $rule;
    }
}

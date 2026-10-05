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

use Exception;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Mageplaza\RMA\Model\ResourceModel\Rule as RuleResource;
use Mageplaza\RMA\Model\Rule;
use Mageplaza\RMA\Model\RuleFactory;
use RuntimeException;

/**
 * Class InlineEdit
 * @package Mageplaza\RMA\Controller\Adminhtml\Rule
 */
class InlineEdit extends Action
{
    /** Authorization level of a basic admin session */
    const ADMIN_RESOURCE = 'Mageplaza_RMA::rule';

    /**
     * @var JsonFactory
     */
    public $jsonFactory;

    /**
     * @var RuleFactory
     */
    public $ruleFactory;

    /**
     * @var RuleResource
     */
    protected $_ruleResource;

    /**
     * InlineEdit constructor.
     *
     * @param Context $context
     * @param JsonFactory $jsonFactory
     * @param RuleFactory $ruleFactory
     * @param RuleResource $ruleResource
     */
    public function __construct(
        Context $context,
        JsonFactory $jsonFactory,
        RuleFactory $ruleFactory,
        RuleResource $ruleResource
    ) {
        $this->jsonFactory = $jsonFactory;
        $this->ruleFactory = $ruleFactory;
        $this->_ruleResource = $ruleResource;

        parent::__construct($context);
    }

    /**
     * @return ResponseInterface|Json|ResultInterface
     */
    public function execute()
    {
        /** @var Json $resultJson */
        $resultJson = $this->jsonFactory->create();
        $error = false;
        $messages = [];
        $ruleItems = $this->getRequest()->getParam('items', []);
        if (!(!empty($ruleItems) && $this->getRequest()->getParam('isAjax'))) {
            return $resultJson->setData([
                'messages' => [__('Please correct the data sent.')],
                'error' => true,
            ]);
        }

        $key = array_keys($ruleItems);
        $ruleId = !empty($key) ? (int)$key[0] : '';
        /** @var Rule $rule */
        $rule = $this->ruleFactory->create();
        $this->_ruleResource->load($rule, $ruleId);
        try {
            $ruleData = $ruleItems[$ruleId];
            $rule->addData($ruleData);
            $this->_ruleResource->save($rule);
        } catch (RuntimeException $e) {
            $messages[] = $this->getErrorWithRuleId($rule, $e->getMessage());
            $error = true;
        } catch (Exception $e) {
            $messages[] = $this->getErrorWithRuleId(
                $rule,
                __('Something went wrong while saving the Rule.')
            );
            $error = true;
        }

        return $resultJson->setData([
            'messages' => $messages,
            'error' => $error
        ]);
    }

    /**
     * Add Rule id to error message
     *
     * @param Rule $rule
     * @param string $errorText
     *
     * @return string
     */
    public function getErrorWithRuleId($rule, $errorText)
    {
        return '[Rule ID: ' . $rule->getId() . '] ' . $errorText;
    }
}

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
use Magento\Backend\App\Action\Context;
use Magento\Backend\Helper\Js;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Registry;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Store\Model\StoreManagerInterface;
use Mageplaza\RMA\Controller\Adminhtml\Rule;
use Mageplaza\RMA\Model\ResourceModel\Rule as RuleResource;
use Mageplaza\RMA\Model\Rule as RuleModel;
use Mageplaza\RMA\Model\RuleFactory;
use RuntimeException;

/**
 * Class Save
 * @package Mageplaza\RMA\Controller\Adminhtml\Rule
 */
class Save extends Rule
{
    /**
     * @var Js
     */
    public $jsHelper;

    /**
     * @var DateTime
     */
    public $date;

    /**
     * @var StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * Save constructor.
     *
     * @param Context $context
     * @param Registry $coreRegistry
     * @param Js $jsHelper
     * @param DateTime $date
     * @param StoreManagerInterface $storeManager
     * @param RuleFactory $ruleFactory
     * @param RuleResource $ruleResource
     */
    public function __construct(
        Context $context,
        Registry $coreRegistry,
        Js $jsHelper,
        DateTime $date,
        StoreManagerInterface $storeManager,
        RuleFactory $ruleFactory,
        RuleResource $ruleResource
    ) {
        $this->jsHelper = $jsHelper;
        $this->date = $date;
        $this->_storeManager = $storeManager;

        parent::__construct(
            $context,
            $coreRegistry,
            $ruleFactory,
            $ruleResource
        );
    }

    /**
     * @return ResponseInterface|Redirect|ResultInterface
     * @throws LocalizedException
     */
    public function execute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();

        if ($data = $this->getRequest()->getPost('rule')) {
            /** @var RuleModel $rule */
            $rule = $this->initRule();
            $this->_prepareData($rule, $data);

            /** get rule conditions */
            $rule->loadPost($data);
            $this->_eventManager->dispatch('mageplaza_rma_rule_prepare_save', [
                'post' => $rule,
                'request' => $this->getRequest()
            ]);

            try {
                $this->_ruleResource->save($rule);
                $this->messageManager->addSuccessMessage(__('The rule has been saved.'));
                $this->_getSession()->setData('mageplaza_rma_rule_data', false);

                if ($this->getRequest()->getParam('back')) {
                    $resultRedirect->setPath('*/*/edit', ['id' => $rule->getId(), '_current' => true]);
                } else {
                    $resultRedirect->setPath('*/*/');
                }

                return $resultRedirect;
            } catch (LocalizedException $e) {
                $this->messageManager->addErrorMessage($e->getMessage());
            } catch (RuntimeException $e) {
                $this->messageManager->addErrorMessage($e->getMessage());
            } catch (Exception $e) {
                $this->messageManager->addExceptionMessage($e, __('Something went wrong while saving the Rule.'));
            }

            $this->_getSession()->setData('mageplaza_rma_rule_data', $data);

            $resultRedirect->setPath('*/*/edit', ['id' => $rule->getId(), '_current' => true]);

            return $resultRedirect;
        }

        $resultRedirect->setPath('*/*/');

        return $resultRedirect;
    }

    /**
     * Set specific data
     *
     * @param RuleModel $rule
     * @param array $data
     *
     * @return $this
     * @throws LocalizedException
     */
    protected function _prepareData($rule, $data = [])
    {
        if ($rule->getCreatedAt() === null) {
            $data['created_at'] = $this->date->date();
        }
        $data['updated_at'] = $this->date->date();
        if (!isset($data['websites'])) {
            $data['websites'] = $this->_storeManager->getWebsite()->getId();
        }
        if (!isset($data['customer_group'])) {
            $data['customer_group'] = 0;
        }
        $rule->addData($data);

        return $this;
    }
}

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

use Exception;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Registry;
use Magento\Framework\Stdlib\DateTime\DateTime as MagentoDatetime;
use Mageplaza\ExtraFee\Controller\Adminhtml\AbstractManageRules;
use Mageplaza\ExtraFee\Helper\Data;
use Mageplaza\ExtraFee\Model\RuleFactory;
use RuntimeException;

/**
 * Class Save
 * @package Mageplaza\ExtraFee\Controller\Adminhtml\ManageRules
 */
class Save extends AbstractManageRules
{
    /**
     * @var Data
     */
    protected $helperData;

    /**
     * @var MagentoDatetime
     */
    protected $dateTime;

    /**
     * Save constructor.
     *
     * @param RuleFactory $ruleFactory
     * @param Registry $coreRegistry
     * @param Context $context
     * @param Data $helperData
     * @param MagentoDatetime $dateTime
     */
    public function __construct(
        RuleFactory $ruleFactory,
        Registry $coreRegistry,
        Context $context,
        Data $helperData,
        MagentoDatetime $dateTime
    ) {
        $this->helperData = $helperData;
        $this->dateTime   = $dateTime;

        parent::__construct($ruleFactory, $coreRegistry, $context);

    }

    /**
     * @return ResponseInterface|Redirect|ResultInterface
     * @throws Exception
     */
    public function execute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();

        $data           = $this->getRequest()->getPost('rule');
        $option         = $this->getRequest()->getPost('option');
        $default        = $this->getRequest()->getPost('default') ?: [];
        $validate       = $this->getRequest()->getPost('dropdown_attribute_validation');
        $validateUnique = $this->getRequest()->getPost('dropdown_attribute_validation_unique');
        $labels         = $this->getRequest()->getPost('frontend_label');
        if (!isset($data['fee_include'])) {
            $data['fee_include'] = null;
        } elseif (!empty($data['fee_include'])) {
            $data['fee_include'] = implode(',', $data['fee_include']);
        }

        if (!isset($data['available_days'])) {
            $data['available_days'] = null;
        } elseif (!empty($data['available_days'])) {
            $data['available_days'] = implode(',', $data['available_days']);
        }

        if ($data['apply_for'] == 1) {
            foreach ($data['actions'] as $key => $value) {
                if ($key != 1) {
                    unset($data['actions'][$key]);
                } else {
                    $data['actions'][$key] = [
                        'aggregator' => 'all',
                        'value'      => '1',
                        'new_child'  => ''
                    ];
                }
            }
        }

        if ($option && !empty($option['value']) && is_array($option['value'])) {
            foreach ($option['value'] as $key => &$item) {
                $item['amount'] = round($item['amount'], 2);
                if ($option['delete'][$key]) {
                    unset($option['value'][$key]);
                }
            }
            unset($item);
        }
        foreach ($default as $key => $value) {
            if (!empty($option['delete'][$value])) {
                unset($default[$key]);
            }
        }
        $options = [
            'option'                               => $option,
            'default'                              => $default,
            'dropdown_attribute_validation'        => $validate,
            'dropdown_attribute_validation_unique' => $validateUnique,
        ];

        $data['options'] = Data::jsonEncode($options);

        if (!empty($labels)) {
            $data['labels'] = Data::jsonEncode($labels);
        }

        $rule = $this->initRule();
        $rule->addData($data);
        $rule->loadPost($data);
        if (!empty($rule->getData('customer_groups'))) {
            $rule->setData('customer_groups', implode(',', $data['customer_groups']));
        }
        if (!empty($rule->getData('store_ids'))) {
            $rule->setData('store_ids', implode(',', $data['store_ids']));
        }

        if ($rule->getData('amount')) {
            $rule->setData('amount', round($rule->getData('amount'), 2));
        }

        if ($rule->getData('allow_note_message') && !$rule->getData('message_title')) {
            $rule->setData('message_title', __('Leave a message with the extra fee.'));
        }

        $fromDate = $rule->getFromDate();
        $toDate   = $rule->getToDate();

        if ($fromDate && $toDate && $fromDate > $toDate) {
            $this->messageManager->addErrorMessage(
                __('The "From Date" cannot be greater than the "To Date".')
            );

            return $resultRedirect->setPath(
                'mpextrafee/*/edit',
                ['rule_id' => $rule->getId(), '_current' => true]
            );
        }

        if (!$fromDate) {
            $rule->setData('from_date', $this->helperData->getCurrentTime());
        }

        if ($toDate) {
            $toDate->setTime(23, 59, 59);
            $rule->setToDate($toDate);
        }

        try {
            $rule->save();
            $this->messageManager->addSuccessMessage(__('The Rule has been saved.'));
            $this->_getSession()->setData('mageplaza_extrafee_rule_data', false);

            if ($this->getRequest()->getParam('back')) {
                $resultRedirect->setPath('mpextrafee/*/edit', ['rule_id' => $rule->getId(), '_current' => true]);
            } else {
                $resultRedirect->setPath('mpextrafee/*/');
            }

            return $resultRedirect;
        } catch (RuntimeException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (Exception $e) {
            $this->messageManager->addExceptionMessage($e, __('Something went wrong while saving the Rule.'));
        }

        $this->_getSession()->setData('mageplaza_extrafee_rule_data', $data);

        $resultRedirect->setPath('mpextrafee/*/edit', ['rule_id' => $rule->getId(), '_current' => true]);

        return $resultRedirect;
    }
}

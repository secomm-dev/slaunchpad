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

namespace Mageplaza\ThankYouPage\Controller\Adminhtml\Condition;

use Magento\Backend\App\Action;
use Magento\Rule\Model\Condition\AbstractCondition;

/**
 * Class NewConditionHtml
 * @package Mageplaza\ThankYouPage\Controller\Adminhtml\Condition
 */
class NewConditionHtml extends Action
{
    /**
     * @return void
     */
    public function execute()
    {
        $conditionId = $this->getRequest()->getParam('id');
        $formName    = $this->getRequest()->getParam('form_namespace');
        $typeArr     = explode('|', str_replace('-', '/', $this->getRequest()->getParam('type')));
        $type        = $typeArr[0];
        $model       = $this->_objectManager->create($type)
            ->setId($conditionId)
            ->setType($type)
            ->setRule($this->_objectManager->create('Mageplaza\ThankYouPage\Model\Template'))
            ->setPrefix('conditions');

        if (!empty($typeArr[1])) {
            $model->setAttribute($typeArr[1]);
        }

        if ($model instanceof AbstractCondition) {
            $model->setJsFormObject($this->getRequest()->getParam('form'));
            $model->setFormName($formName);
            $html = $model->asHtmlRecursive();
        } else {
            $html = '';
        }
        $this->getResponse()->setBody($html);
    }
}

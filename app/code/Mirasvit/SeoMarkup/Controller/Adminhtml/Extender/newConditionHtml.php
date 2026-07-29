<?php
/**
 * Mirasvit
 *
 * This source file is subject to the Mirasvit Software License, which is available at https://mirasvit.com/license/.
 * Do not edit or add to this file if you wish to upgrade the to newer versions in the future.
 * If you wish to customize this module for your needs.
 * Please refer to http://www.magentocommerce.com for more information.
 *
 * @category  Mirasvit
 * @package   mirasvit/module-seo
 * @version   2.12.8
 * @copyright Copyright (C) 2026 Mirasvit (https://mirasvit.com/)
 */


declare(strict_types=1);

namespace Mirasvit\SeoMarkup\Controller\Adminhtml\Extender;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Rule\Model\Condition\AbstractCondition;
use Mirasvit\SeoMarkup\Api\Data\ExtenderInterfaceFactory as Factory;
use Mirasvit\SeoMarkup\Api\Data\ExtenderInterface;

class newConditionHtml extends Action
{
    private $factory;

    public function __construct(
        Factory $factory,
        Context $context
    ) {
        parent::__construct($context);

        $this->factory = $factory;
    }

    public function execute(): ResponseInterface
    {
        $id      = $this->getRequest()->getParam(ExtenderInterface::REQUEST_PARAM_ID);
        $typeArr = explode('|', str_replace('-', '/', $this->getRequest()->getParam('type')));
        $type    = $typeArr[0];

        $model = $this->_objectManager->create($type)
            ->setId($id)
            ->setType($type)
            ->setRule($this->factory->create())
            ->setPrefix('conditions')
            ->setFormName($this->getRequest()->getParam('ruleform'));

        if (!empty($typeArr[1])) {
            $model->setAttribute($typeArr[1]);
        }

        if ($model instanceof AbstractCondition) {
            $html = $model->asHtmlRecursive();
        } else {
            $html = '';
        }

        return $this->getResponse()->setBody($html);
    }
}

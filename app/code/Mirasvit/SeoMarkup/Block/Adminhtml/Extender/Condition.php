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

namespace Mirasvit\SeoMarkup\Block\Adminhtml\Extender;

use Magento\Backend\Block\Widget\Context;
use Magento\Backend\Block\Widget\Form;
use Magento\Backend\Block\Widget\Form\Renderer\Fieldset;
use Magento\Backend\Block\Widget\Tab\TabInterface;
use Magento\Backend\Model\Url;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Data\FormFactory;
use Magento\Rule\Block\Conditions;
use Magento\Rule\Model\Condition\AbstractCondition;
use Mirasvit\SeoMarkup\Api\Data\ExtenderInterface;
use Mirasvit\Seo\Api\Data\CanonicalRewriteInterfaceFactory as Factory;
use Mirasvit\SeoMarkup\Repository\ExtenderRepository;

class Condition extends Form implements TabInterface
{
    public const CONDITION_FORM_NAME = 'extender_edit_form';
    public const RULE_FIELDSET_NAME  = 'rule_conditions_fieldset';
    public const HTML_ID_PREFIX      = 'rule_';

    private $fieldsetRenderer;

    private $conditions;

    private $formFactory;

    private $url;

    private $factory;

    private $repository;

    private $dataPersistor;

    /**
     * @var string
     */
    protected $_nameInLayout = 'conditions_serialized';

    public function __construct(
        Fieldset               $fieldsetRenderer,
        Conditions             $conditions,
        FormFactory            $formFactory,
        Url                    $url,
        Factory                $factory,
        ExtenderRepository     $repository,
        DataPersistorInterface $dataPersistor,
        Context                $context,
        array                  $data = []
    ) {
        $this->fieldsetRenderer = $fieldsetRenderer;
        $this->conditions       = $conditions;
        $this->formFactory      = $formFactory;
        $this->url              = $url;
        $this->factory          = $factory;
        $this->repository       = $repository;
        $this->dataPersistor    = $dataPersistor;

        parent::__construct($context, $data);
    }

    public function getTabLabel(): string
    {
        return (string)__('Conditions');
    }

    public function getTabTitle(): string
    {
        return (string)__('Conditions');
    }

    public function canShowTab(): bool
    {
        return true;
    }

    public function isHidden(): bool
    {
        return false;
    }

    protected function _prepareForm(): Condition
    {
        $extender = $this->factory->create();

        $extenderId = $this->getRequest()->getParam(ExtenderInterface::REQUEST_PARAM_ID);
        if ($extenderId) {
            $extender = $this->repository->get((int)$extenderId);
        }

        $restoredData       = $this->dataPersistor->get(ExtenderInterface::DATA_PERSISTOR_KEY);
        $restoredExtenderId = $restoredData[ExtenderInterface::EXTENDER_ID] ?? 0;
        if ((int)$extenderId === (int)$restoredExtenderId) {
            foreach (ExtenderInterface::ATTRIBUTES as $attributeName) {
                if (isset($restoredData[$attributeName])) {
                    $extender->setDataUsingMethod($attributeName, $restoredData[$attributeName]);
                }
            }
        }

        $form = $this->formFactory->create();

        $form->setHtmlIdPrefix(self::HTML_ID_PREFIX);
        $renderer = $this->fieldsetRenderer
            ->setTemplate('Magento_CatalogRule::promo/fieldset.phtml')
            ->setNameInLayout('mst_seomarkup_extender_condition')
            ->setNewChildUrl($this->url->getUrl('*/*/newConditionHtml/form/' . self::RULE_FIELDSET_NAME))
            ->setFieldSetId(self::RULE_FIELDSET_NAME);

        if ($url = $renderer->getData('new_child_url')) {
            $renderer->setData('new_child_url', $url . '?ruleform=' . self::CONDITION_FORM_NAME);
        }

        $fieldset = $form
            ->addFieldset(self::RULE_FIELDSET_NAME, [])
            ->setRenderer($renderer);

        $extender->getConditions()
            ->setFormName(self::CONDITION_FORM_NAME)
            ->setJsFormObject(self::RULE_FIELDSET_NAME);

        $fieldset
            ->addField(
                ExtenderInterface::CONDITIONS,
                'text',
                [
                    'name'           => ExtenderInterface::CONDITIONS,
                    'required'       => true,
                    'data-form-part' => self::CONDITION_FORM_NAME,
                ]
            )
            ->setRule($extender)
            ->setRenderer($this->conditions)
            ->setFormName(self::CONDITION_FORM_NAME);

        $form->setValues($extender->getData());
        $this->setConditionFormName($extender->getConditions(), self::CONDITION_FORM_NAME);

        $this->setForm($form);

        return parent::_prepareForm();
    }

    private function setConditionFormName(AbstractCondition $conditions, string $formName)
    {
        $conditions->setFormName($formName);
        $conditionList = $conditions->getConditions();
        if (is_array($conditionList)) {
            foreach ($conditionList as $condition) {
                $this->setConditionFormName($condition, $formName);
            }
        }
    }
}

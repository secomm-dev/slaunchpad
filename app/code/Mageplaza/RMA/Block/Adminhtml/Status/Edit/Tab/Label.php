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

namespace Mageplaza\RMA\Block\Adminhtml\Status\Edit\Tab;

use Magento\Backend\Block\Store\Switcher\Form\Renderer\Fieldset;
use Magento\Backend\Block\Widget\Form\Generic;
use Magento\Backend\Block\Widget\Tab\TabInterface;
use Magento\Framework\Data\Form;
use Magento\Framework\Data\Form\Element\Renderer\RendererInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\Group;
use Magento\Store\Model\Store;
use Magento\Store\Model\Website;
use Mageplaza\RMA\Model\Status;

/**
 * Class Label
 * @package Mageplaza\RMA\Block\Adminhtml\Status\Edit\Tab
 */
class Label extends Generic implements TabInterface
{
    /**
     * @inheritdoc
     * @return Generic
     * @throws LocalizedException
     */
    protected function _prepareForm()
    {
        /** @var Status $status */
        $status = $this->_coreRegistry->registry('mageplaza_rma_status');

        /** @var Form $form */
        $form = $this->_formFactory->create();

        $form->setHtmlIdPrefix('status_');
        $form->setFieldNameSuffix('status');

        $fieldset = $form->addFieldset(
            'default_label_fieldset',
            ['class' => 'fieldset-wide', 'legend' => __('Default Label')]
        );

        $fieldset->addField('label', 'text', [
            'name' => 'label',
            'label' => __('Default Status Label for All Store Views'),
            'title' => __('Default Status Label for All Store Views'),
            'required' => true
        ]);

        if (!$this->_storeManager->isSingleStoreMode()) {
            $this->_addStoresFieldset($status, $form);
        }

        $form->addValues($status->getData());
        $this->setForm($form);

        return parent::_prepareForm();
    }

    /**
     * @param Status $status
     * @param Form $form
     *
     * @throws LocalizedException
     */
    protected function _addStoresFieldset($status, $form)
    {
        $labels = $status ? $status->getStoreLabels() : [];
        $fieldset = $form->addFieldset('store_labels_fieldset', [
            'legend' => __('Store View Specific Labels'),
            'class' => 'store-scope'
        ]);
        /** @var RendererInterface $renderer */
        $renderer = $this->getLayout()->createBlock(Fieldset::class);
        $fieldset->setRenderer($renderer);

        foreach ($this->_storeManager->getWebsites() as $website) {
            $fieldset->addField("w_{$website->getId()}_label", 'note', [
                'label' => $website->getName(),
                'fieldset_html_class' => 'website'
            ]);
            /** @var Website $website */
            foreach ($website->getGroups() as $group) {
                /** @var Group $group */
                $stores = $group->getStores();
                if (count($stores) === 0) {
                    continue;
                }
                $fieldset->addField("sg_{$group->getId()}_label", 'note', [
                    'label' => $group->getName(),
                    'fieldset_html_class' => 'store-group'
                ]);
                /** @var Store $store */
                foreach ($stores as $store) {
                    $fieldset->addField("store_label_{$store->getId()}", 'text', [
                        'name' => 'store_labels[' . $store->getId() . ']',
                        'required' => false,
                        'label' => $store->getName(),
                        'value' => isset($labels[$store->getId()]) ? $labels[$store->getId()] : '',
                        'fieldset_html_class' => 'store'
                    ]);
                }
            }
        }
    }

    /**
     * Prepare label for tab
     *
     * @return string
     */
    public function getTabLabel()
    {
        return __('Label');
    }

    /**
     * Prepare title for tab
     *
     * @return string
     */
    public function getTabTitle()
    {
        return $this->getTabLabel();
    }

    /**
     * Can show tab in tabs
     *
     * @return boolean
     */
    public function canShowTab()
    {
        return true;
    }

    /**
     * Tab is hidden
     *
     * @return boolean
     */
    public function isHidden()
    {
        return false;
    }
}

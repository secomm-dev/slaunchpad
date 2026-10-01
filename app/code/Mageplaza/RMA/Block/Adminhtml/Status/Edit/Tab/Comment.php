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
use Magento\Backend\Block\Template\Context;
use Magento\Backend\Block\Widget\Form\Generic;
use Magento\Backend\Block\Widget\Tab\TabInterface;
use Magento\Config\Model\Config\Source\Yesno;
use Magento\Framework\Data\Form;
use Magento\Framework\Data\Form\Element\Renderer\RendererInterface;
use Magento\Framework\Data\FormFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Registry;
use Magento\Store\Model\Group;
use Magento\Store\Model\Store;
use Magento\Store\Model\Website;
use Mageplaza\RMA\Model\Status;

/**
 * Class Comment
 * @package Mageplaza\RMA\Block\Adminhtml\Status\Edit\Tab
 */
class Comment extends Generic implements TabInterface
{
    /**
     * @var string
     */
    protected $_hiddenClass = '';

    /**
     * @var Yesno
     */
    protected $_yesNo;

    /**
     * Comment constructor.
     *
     * @param Context $context
     * @param Registry $registry
     * @param FormFactory $formFactory
     * @param Yesno $yesNo
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        FormFactory $formFactory,
        Yesno $yesNo,
        array $data = []
    ) {
        $this->_yesNo = $yesNo;

        parent::__construct(
            $context,
            $registry,
            $formFactory,
            $data
        );
    }

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
        $fieldset = $form->addFieldset('base_fieldset', ['class' => 'fieldset-wide']);

        $fieldset->addField('enable_comment', 'select', [
            'name' => 'enable_comment',
            'label' => __('Enable Default Comment'),
            'title' => __('Enable Default Comment'),
            'values' => $this->_yesNo->toOptionArray(),
            'onclick' => 'mpRMAFormBefore.isEnableStatusComment(event);'
        ]);
        if (!$status->hasData('enable_comment')) {
            $status->setEnableComment(1);
        }
        if (!$status->getEnableComment()) {
            $this->_hiddenClass = 'hidden';
        }

        $commentFieldset = $form->addFieldset('default_comment_fieldset', [
            'class' => 'fieldset-wide ' . $this->_hiddenClass,
            'legend' => __('Default Comment')
        ]);

        $commentFieldset->addField('comment', 'text', [
            'name' => 'comment',
            'disabled' => !$status->getEnableComment(),
            'label' => __('Default Comment for All Store Views'),
            'title' => __('Default Comment for All Store Views'),
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
        $comments = $status ? $status->getStoreComments() : [];

        $fieldset = $form->addFieldset('store_comments_fieldset', [
            'legend' => __('Store View Specific Comments'),
            'class' => 'store-scope ' . $this->_hiddenClass
        ]);
        /** @var RendererInterface $renderer */
        $renderer = $this->getLayout()->createBlock(Fieldset::class);
        $fieldset->setRenderer($renderer);

        foreach ($this->_storeManager->getWebsites() as $website) {
            $fieldset->addField("w_{$website->getId()}_comment", 'note', [
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
                $fieldset->addField("sg_{$group->getId()}_comment", 'note', [
                    'label' => $group->getName(),
                    'fieldset_html_class' => 'store-group'
                ]);
                /** @var Store $store */
                foreach ($stores as $store) {
                    $fieldset->addField("store_comment_{$store->getId()}", 'text', [
                        'name' => 'store_comments[' . $store->getId() . ']',
                        'required' => false,
                        'disabled' => !$status->getEnableComment(),
                        'label' => $store->getName(),
                        'value' => isset($comments[$store->getId()]) ? $comments[$store->getId()] : '',
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
        return __('Comment/Reply');
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

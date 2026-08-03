<?php
/**
 * Magezon
 *
 * This source file is subject to the Magezon Software License, which is available at https://www.magezon.com/license
 * Do not edit or add to this file if you wish to upgrade the to newer versions in the future.
 * If you wish to customize this module for your needs.
 * Please refer to https://www.magezon.com for more information.
 *
 * @category  Magezon
 * @package   Magezon_PageBuilder
 * @copyright Copyright (C) 2019 Magezon (https://www.magezon.com)
 */

namespace Magezon\PageBuilder\Block\Element;

class ContactForm extends \Magezon\Builder\Block\Element
{
    /**
     * @return string
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getContactFormHtml()
    {
        $uniqId = uniqid('contactForm', true);
        $sanitizedUniqId = str_replace([',', '.'], ['', ''], $uniqId);

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $viewModel = $objectManager->get(\Magento\Contact\ViewModel\UserDataProvider::class);
        $buttonLockManager = null;
        $buttonLockManagerClass = '\Magento\Framework\View\Element\ButtonLockManager';
        if (class_exists($buttonLockManagerClass)) {
            $buttonLockManager = \Magento\Framework\App\ObjectManager::getInstance()->get($buttonLockManagerClass);
        }

        $layout = $this->getLayout();

        $contactForm = $layout->createBlock(
            \Magento\Contact\Block\ContactForm::class,
            $sanitizedUniqId,
            [
                'data' => [
                    'view_model' => \Magento\Framework\App\ObjectManager::getInstance()->get(\Magento\Contact\ViewModel\UserDataProvider::class),
                    'button_lock_manager' => $buttonLockManager
                ]
            ]
        )->setTemplate('Magezon_Builder::contact/form.phtml');

        $formAdditional = $layout->createBlock(
            \Magento\Framework\View\Element\Text\ListText::class,
            $sanitizedUniqId . '_form_additional'
        );

        $contactForm->setChild('form.additional.info', $formAdditional);

        $recaptcha = $layout->createBlock(
            \Magento\ReCaptchaUi\Block\ReCaptcha::class,
            $sanitizedUniqId . '_recaptcha',
            [
                'data' => [
                    'recaptcha_for' => 'contact',
                    'jsLayout' => [
                        'components' => [
                            'recaptcha' => [
                                'component' => 'Magento_ReCaptchaFrontendUi/js/reCaptcha'
                            ]
                        ]
                    ]
                ]
            ]
        );

        $recaptcha->setTemplate('Magento_ReCaptchaFrontendUi::recaptcha.phtml');

        $formAdditional->setChild('recaptcha', $recaptcha);

        return $contactForm->toHtml();
    }

    /**
     * @return string
     */
    public function getAdditionalStyleHtml()
    {
        $styleHtml = '';
        $element = $this->getElement();

        $styles = [];
        $styles['width'] = $this->getStyleProperty($element->getData('form_width'), true);
        $styleHtml .= $this->getStyles('.form.contact', $styles);

        if (!$element->getData('show_title')) {
            $styles = [];
            $styles['display'] = 'none';
            $styleHtml .= $this->getStyles('.form.contact .legend', $styles);
        }

        if (!$element->getData('show_description')) {
            $styles = [];
            $styles['display'] = 'none';
            $styleHtml .= $this->getStyles('.field.note', $styles);
        }

        return $styleHtml;
    }
}

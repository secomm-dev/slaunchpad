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

namespace Mageplaza\ThankYouPage\Controller\Adminhtml\ManageStyle;

use Exception;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Filesystem;
use Mageplaza\ThankYouPage\Helper\Data as HelperData;

/**
 * Class Load
 * @package Mageplaza\ThankYouPage\Controller\Adminhtml\ManageStyle
 */
class Load extends Action
{
    /**
     * @var JsonFactory
     */
    protected $resultJsonFactory;

    /**
     * @var Filesystem
     */
    protected $filesystem;

    /**
     * @var HelperData
     */
    protected $helperData;

    /**
     * Load constructor.
     *
     * @param Context $context
     * @param Filesystem $filesystem
     * @param JsonFactory $resultJsonFactory
     * @param HelperData $helperData
     */
    public function __construct(
        Context $context,
        Filesystem $filesystem,
        JsonFactory $resultJsonFactory,
        HelperData $helperData
    ) {
        $this->filesystem        = $filesystem;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->helperData        = $helperData;

        parent::__construct($context);
    }

    /**
     * @return ResponseInterface|Json|ResultInterface
     */
    public function execute()
    {
        $templateHtml     = $templateCss = '';
        $hyvaTemplateHtml = $hyvaTemplateCss = '';
        $templateId       = $this->getRequest()->getParam('templateId', 'template1');

        try {
            $templateHtml     = $this->helperData->getDefaultTemplateHtml('order', $templateId);
            $templateCss      = $this->helperData->getDefaultTemplateCss('order', $templateId);
            $hyvaTemplateHtml = $this->helperData->getDefaultTemplateHtml('order', 'hyva_' . $templateId);
            $hyvaTemplateCss  = $this->helperData->getDefaultTemplateCss('order', 'hyva_' . $templateId);
            $status           = true;
            $message          = __('Load template success!');
        } catch (Exception $e) {
            $status  = false;
            $message = __("Cannot load template.");
        }

        /** @var Json $result */
        $result = $this->resultJsonFactory->create();

        return $result->setData([
            'status'           => $status,
            'message'          => $message,
            'templateHtml'     => $templateHtml,
            'templateCss'      => $templateCss,
            'hyvaTemplateHtml' => $hyvaTemplateHtml,
            'hyvaTemplateCss'  => $hyvaTemplateCss
        ]);
    }
}

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

namespace Mageplaza\RMA\Controller\Adminhtml\Request\Template;

use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Request\Http;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\ForwardFactory;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Registry;
use Magento\Framework\View\Layout;
use Mageplaza\RMA\Block\Adminhtml\Request\Edit\Form\Template;
use Mageplaza\RMA\Controller\Adminhtml\Request\Template as AbstractTemplate;
use Mageplaza\RMA\Model\ResourceModel\Template as TemplateResource;
use Mageplaza\RMA\Model\ResourceModel\Template\Collection;
use Mageplaza\RMA\Model\ResourceModel\Template\CollectionFactory;
use Mageplaza\RMA\Model\TemplateFactory;

/**
 * Class Load
 * @package Mageplaza\RMA\Controller\Adminhtml\Request\Template
 */
class Load extends AbstractTemplate
{
    /**
     * @var CollectionFactory
     */
    protected $_templateColFact;

    /**
     * Load constructor.
     *
     * @param Context $context
     * @param Registry $coreRegistry
     * @param Layout $layout
     * @param Json $resultJson
     * @param ForwardFactory $resultForwardFactory
     * @param TemplateFactory $templateFactory
     * @param TemplateResource $templateResource
     * @param CollectionFactory $templateColFact
     */
    public function __construct(
        Context $context,
        Registry $coreRegistry,
        Layout $layout,
        Json $resultJson,
        ForwardFactory $resultForwardFactory,
        TemplateFactory $templateFactory,
        TemplateResource $templateResource,
        CollectionFactory $templateColFact
    ) {
        $this->_templateColFact = $templateColFact;

        parent::__construct(
            $context,
            $coreRegistry,
            $layout,
            $resultJson,
            $resultForwardFactory,
            $templateFactory,
            $templateResource
        );
    }

    /**
     * @return ResponseInterface|Json|ResultInterface
     */
    public function execute()
    {
        /** @var Http $request */
        $request = $this->getRequest();
        if (!$request->isAjax()) {
            return $this->_resultFwFactory->create()->forward('noroute');
        }
        /** @var Template $templateBlock */
        $templateBlock = $this->_layout->createBlock(Template::class);
        if ($request->getParam('template_type') === Template::TEMPLATE_INDEX_PAGE) {
            /** @var Collection $collection */
            $collection = $this->_templateColFact->create();
            $collection->setOrder('main_table.created_at', 'desc');
            $storeId = $request->getParam('store_id', 0);
            $templateBlock->setTemplateCollection($collection)
                ->setTemplateStoreId($storeId)
                ->setTemplate('Mageplaza_RMA::request/form/template/index.phtml');
        } else {
            /** @var \Mageplaza\RMA\Model\Template $template */
            $template = $this->initTemplate();
            $templateBlock
                ->setTemplateModel($template)
                ->setTemplate('Mageplaza_RMA::request/form/template/edit.phtml');
        }

        $result = [
            'status' => true,
            'template_html' => $templateBlock->toHtml()
        ];

        return $this->_resultJson->setData($result);
    }
}

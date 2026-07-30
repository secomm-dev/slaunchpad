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



namespace Mirasvit\Seo\Model;
/**
 * Class Observer
 * @package Mirasvit\Seo\Model
 * @SuppressWarnings(PHPMD)
 */
//@codingStandardsIgnoreFile
class Observer
{
    /**
     * @var \Mirasvit\Seo\Model\RedirectFactory
     */
    protected $redirectFactory;

    /**
     * @var \Magento\Catalog\Model\LayerFactory
     */
    protected $layerFactory;

    /**
     * @var \Magento\Catalog\Model\CategoryFactory
     */
    protected $categoryFactory;

    /**
     * @var \Magento\Catalog\Model\ProductFactory
     */
    protected $productFactory;


    /**
     * @var \Mirasvit\Seo\Model\System\Template\WorkerFactory
     */
    protected $systemTemplateWorkerFactory;

    /**
     * @var \Mirasvit\Seo\Model\SeoObject\ProducturlFactory
     */
    protected $objectProducturlFactory;

    /**
     * @var \Magento\Eav\Model\ResourceModel\Entity\Attribute\Group\CollectionFactory
     */
    protected $entityAttributeGroupCollectionFactory;

    /**
     * @var \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory
     */
    protected $productCollectionFactory;

    /**
     * @var \Mirasvit\Seo\Model\Config
     */
    protected $config;

    /**
     * @var \Magento\Catalog\Model\Layer
     */
    protected $layer;


    /**
     * @var \Magento\Catalog\Model\Product\Url
     */
    protected $productUrl;

    /**
     * @var \Mirasvit\Seo\Model\System\Template\Worker
     */
    protected $systemTemplateWorker;

    /**
     * @var mixed
     */
    protected $catalogProductFlat;

    /**
     * @var \Mirasvit\Seo\Helper\Data
     */
    protected $seoData;

    /**
     * @var \Magento\Framework\App\Request\Http
     */
    protected $request;

    /**
     * @var \Magento\Backend\Block\Widget\Context
     */
    protected $widgetContext;

    /**
     * @var \Magento\Framework\UrlInterface
     */
    protected $urlManager;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var \Magento\Framework\Module\Manager
     */
    protected $moduleManager;

    /**
     * @var \Magento\Framework\Filesystem
     */
    protected $filesystem;

    /**
     * @var \Magento\Framework\View\DesignInterface
     */
    protected $design;

    /**
     * @var \Magento\Framework\App\ResourceConnection
     */
    protected $dbResource;

    /**
     * @var \Magento\Framework\App\ResponseInterface
     */
    protected $response;

    /**
     * @var \Magento\Framework\View\Element\Template\Context
     */
    protected $context;

    /**
     * @var \Magento\Framework\Controller\ResultFactory
     */
    protected $resultFactory;
    /**
     * @var \Magento\Framework\App\ActionFlag
     */
    private $_actionFlag;
    /**
     * @var \Magento\Framework\App\Response\RedirectInterface
     */
    private $redirect;

    /**
     * @param \Mirasvit\Seo\Model\RedirectFactory               $redirectFactory
     * @param \Mirasvit\Seo\Model\Config                        $config
     * @param \Mirasvit\Seo\Helper\Data                         $seoData
     * @param \Magento\Framework\App\Request\Http               $request
     * @param \Magento\Framework\Module\Manager                 $moduleManager
     * @param \Magento\Framework\View\Element\Template\Context  $context
     * @param \Magento\Framework\Controller\ResultFactory       $resultFactory
     * @param \Magento\Framework\App\Response\RedirectInterface $redirect
     * @param \Magento\Framework\App\ActionFlag                 $actionFlag
     */
    public function __construct(
        \Mirasvit\Seo\Model\RedirectFactory $redirectFactory,
        \Mirasvit\Seo\Model\Config $config,
        \Mirasvit\Seo\Helper\Data $seoData,
        \Magento\Framework\App\Request\Http $request,
        \Magento\Framework\Module\Manager $moduleManager,
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Framework\Controller\ResultFactory $resultFactory,
        \Magento\Framework\App\Response\RedirectInterface $redirect,
        \Magento\Framework\App\ActionFlag $actionFlag
    ) {
        $this->redirectFactory = $redirectFactory;
        $this->resultFactory   = $resultFactory;
        $this->redirect        = $redirect;
        $this->_actionFlag     = $actionFlag;
        $this->config          = $config;
        $this->request         = $request;
        $this->seoData         = $seoData;
        $this->urlManager      = $context->getUrlBuilder();
        $this->storeManager    = $context->getStoreManager();
        $this->moduleManager   = $moduleManager;
        $this->design          = $context->getDesignPackage();
    }

    /**
     * @param \Magento\Framework\Event\Observer $e
     */
    public function setupProductUrls($e)
    {
        $collection = $e->getCollection();
        $this->_addUrlRewrite($collection);
    }

    /**
     * Add URL rewrites to collection.
     * @dva we will need this, because:
     * 1. M2 does not do redirects from short to long urls.
     * 2. M2 does not show long urls in block like 'relative products'
     *
     * @param mixed $collection
     */
    protected function _addUrlRewrite($collection)
    {
        $urlFormat = $this->config->getProductUrlFormat();
        if ($urlFormat != Config::URL_FORMAT_SHORT &&
            $urlFormat != Config::URL_FORMAT_LONG) {
            return;
        }

        $items = $collection->getItems();
        if (!count($items)) {
            return;
        }

        foreach ($items as $item) {
            $item->setData('request_path', false);
        }
    }

//    /**
//     * @param string $observer
//     */
//    public function saveProductBefore($observer)
//    {
//        $product = $observer->getProduct();
//        if ($product->getStoreId() == 0
//            //~ && $product->getOrigData('url_key') != $product->getData('url_key')
//
//        ) {
//            $this->systemTemplateWorkerFactory->create()->processProduct($product);
//        }
//    }
}

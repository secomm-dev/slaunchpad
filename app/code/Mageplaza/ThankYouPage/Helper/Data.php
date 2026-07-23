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

namespace Mageplaza\ThankYouPage\Helper;

use Exception;
use Liquid\Template;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Directory\Model\Currency;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Filesystem;
use Magento\Framework\ObjectManagerInterface;
use Magento\Payment\Helper\Data as PaymentHelper;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Address\Renderer;
use Magento\SalesRule\Model\Rule;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use Mageplaza\Core\Helper\AbstractData;
use Mageplaza\ThankYouPage\Model\Config\Source\OrderPageStyle;
use Mageplaza\ThankYouPage\Model\Config\Source\Pagetype;
use Mageplaza\ThankYouPage\Model\Generate;
use Mageplaza\ThankYouPage\Model\ResourceModel\Template\CollectionFactory;
use Mageplaza\ThankYouPage\Model\TemplateFactory;

/**
 * Class Data
 * @package Mageplaza\ThankYouPage\Helper
 */
class Data extends AbstractData
{
    const CONFIG_MODULE_PATH = 'mpthankyoupage';

    /**
     * @var
     */
    public $orderTemplateId;

    /**
     * @var Renderer
     */
    protected $addressRenderer;

    /**
     * @var PaymentHelper
     */
    protected $paymentHelper;

    /**
     * @var LiquidFilter
     */
    protected $liquidFilters;

    /**
     * @var Filesystem
     */
    protected $fileSystem;

    /**
     * @var DirectoryList
     */
    protected $directoryList;

    /**
     * @var OrderPageStyle
     */
    protected $orderPageStyle;

    /**
     * @var Currency
     */
    protected $_currency;

    /**
     * @var Generate
     */
    protected $generateCoupon;

    /**
     * @var CollectionFactory
     */
    protected $collectionFactory;

    /**
     * @var CustomerSession
     */
    protected $customerSession;

    /**
     * @var TemplateFactory
     */
    protected $templateFactory;

    /**
     * Data constructor.
     *
     * @param Context $context
     * @param ObjectManagerInterface $objectManager
     * @param StoreManagerInterface $storeManager
     * @param PaymentHelper $paymentHelper
     * @param LiquidFilter $liquidFilter
     * @param Renderer $addressRenderer
     * @param Filesystem $fileSystem
     * @param DirectoryList $directoryList
     * @param OrderPageStyle $orderPageStyle
     * @param Currency $currency
     * @param Generate $generate
     * @param CollectionFactory $collectionFactory
     * @param CustomerSession $customerSession
     * @param TemplateFactory $templateFactory
     */
    public function __construct(
        Context $context,
        ObjectManagerInterface $objectManager,
        StoreManagerInterface $storeManager,
        PaymentHelper $paymentHelper,
        LiquidFilter $liquidFilter,
        Renderer $addressRenderer,
        Filesystem $fileSystem,
        DirectoryList $directoryList,
        OrderPageStyle $orderPageStyle,
        Currency $currency,
        Generate $generate,
        CollectionFactory $collectionFactory,
        CustomerSession $customerSession,
        TemplateFactory $templateFactory
    ) {
        $this->paymentHelper     = $paymentHelper;
        $this->addressRenderer   = $addressRenderer;
        $this->liquidFilters     = $liquidFilter;
        $this->fileSystem        = $fileSystem;
        $this->directoryList     = $directoryList;
        $this->orderPageStyle    = $orderPageStyle;
        $this->_currency         = $currency;
        $this->generateCoupon    = $generate;
        $this->collectionFactory = $collectionFactory;
        $this->customerSession   = $customerSession;
        $this->templateFactory   = $templateFactory;

        parent::__construct($context, $objectManager, $storeManager);
    }

    /**
     * @return bool
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function checkCustom()
    {
        return (bool) $this->getTemplateData(Pagetype::ORDER)->getCustomStyle();
    }

    /**
     * @param string $page
     * @param null $storeId
     * @param null $customerGroup
     *
     * @return \Mageplaza\ThankYouPage\Model\Template|null
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function getTemplateData($page, $storeId = null, $customerGroup = null)
    {
        $templateId = '';
        $templates  = $this->getActiveTemplates($page, $storeId, $customerGroup);
        foreach ($templates as $template) {
            if ($page === Pagetype::ORDER && $template->getApplyTemplate()) {
                $templateId            = $template->getApplyTemplate();
                $this->orderTemplateId = $templateId;
            } elseif ($page === Pagetype::NEWSLETTER) {
                $templateId = $template->getId();
            }
        }
        if (!empty($templateId)) {
            return $this->templateFactory->create()->load($templateId);
        }

        return null;
    }

    /**
     * @param string $page
     * @param null $storeId
     * @param null $customerGroup
     *
     * @return mixed
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function getActiveTemplates($page, $storeId = null, $customerGroup = null)
    {
        $collection = $this->collectionFactory->create();
        if ($storeId === null) {
            $storeId = $this->getCurrentStore();
        }
        if ($customerGroup === null) {
            $customerGroup = $this->getCustomerGroup();
        }
        $collection->addActiveFilter($customerGroup, $storeId)
            ->setOrder('template_id', 'DESC')
            ->addPageTypeFilter($page);

        return $collection;
    }

    /**
     * @return mixed|null
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function getCustomerGroup()
    {
        return $this->customerSession->getCustomerGroupId();
    }

    /**
     * @return int
     * @throws NoSuchEntityException
     */
    public function getCurrentStore()
    {
        return $this->storeManager->getStore()->getId();
    }

    /**
     * @param $name
     *
     * @return bool
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function checkEnableBlock($name)
    {
        $block = $this->getTemplateData(Pagetype::ORDER)->getBlock();

        return strpos($block, $name) !== false;
    }

    /**
     * @param string $field
     * @param null $store
     *
     * @return mixed
     */
    public function getOrderSuccessPageConfig($field = '', $store = null)
    {
        $field = ($field !== '') ? '/' . $field : '';

        return $this->getModuleConfig('order_success_page' . $field, $store);
    }

    /**
     * @param null $store
     *
     * @return mixed
     */
    public function isOrderSuccessPageEnable($store = null)
    {
        return $this->getModuleConfig('order_success_page/enabled', $store);
    }

    /**
     * Retrieve route name for order success page.
     * If empty, default 'mpthankyoupage' will be used
     *
     * @param null $store
     *
     * @return string
     */
    public function getOrderSuccessPageRoute($store = null)
    {
        return !empty($this->getModuleConfig('order_success_page/route', $store))
            ? $this->getModuleConfig('order_success_page/route', $store)
            : self::CONFIG_MODULE_PATH;
    }

    /**
     * Retrieve route name for newsletter success page.
     * If empty, default 'subscribe' will be used
     *
     * @param null $store
     *
     * @return string
     */
    public function getNewsletterSuccessPageRoute($store = null)
    {
        return !empty($this->getModuleConfig('newsletter_success_page/route', $store))
            ? $this->getModuleConfig('newsletter_success_page/route', $store)
            : 'subscribe';
    }

    /**
     * @param null $store
     *
     * @return mixed
     */
    public function getSharethisKey($store = null)
    {
        return $this->getConfigGeneral('sharethis_id', $store);
    }

    /**
     * @param $style
     * @param $vars
     *
     * @return string
     */
    public function getOrderDetailHtml($style, $vars)
    {
        $html = $this->orderPageStyle->getOrderDetailHtml();

        return $this->processLiquidTemplate($html[$style], $vars);
    }

    /**
     * @param $templateHtml
     * @param $templateVars
     *
     * @return string
     */
    public function processLiquidTemplate($templateHtml, $templateVars)
    {
        $template = new Template;
        $template->registerFilter($this->liquidFilters);
        $template->parse($templateHtml, $this->liquidFilters->getFiltersMethods());

        return $template->render($templateVars);
    }

    /**
     * @param string $field
     * @param null $store
     *
     * @return mixed
     */
    public function getNewsletterSuccessPageConfig($field = '', $store = null)
    {
        $field = ($field !== '') ? '/' . $field : '';

        return $this->getModuleConfig('newsletter_success_page' . $field, $store);
    }

    /**
     * @param null $store
     *
     * @return mixed
     */
    public function isNewsletterSuccessPageEnable($store = null)
    {
        return $this->getModuleConfig('newsletter_success_page/enabled', $store);
    }

    /**
     * @param \Mageplaza\ThankYouPage\Model\Template $data
     * @param $vars
     *
     * @return string
     */
    public function processCustomHtml($data, $vars)
    {
        $html = $data->getCustomHtml();
        if ($this->checkHyvaTheme()) {
            $html = $data->getHyvaCustomHtml();
        }

        return $this->processLiquidTemplate($html, $vars);
    }

    /**
     * @param Order $order
     *
     * @return null|string
     */
    public function getFormattedShippingAddress($order)
    {
        return $order->getIsVirtual()
            ? null
            : $this->addressRenderer->format($order->getShippingAddress(), 'html');
    }

    /**
     * @param Order $order
     *
     * @return null|string
     */
    public function getFormattedBillingAddress($order)
    {
        return $this->addressRenderer->format($order->getBillingAddress(), 'html');
    }

    /**
     * Return payment info block as html
     *
     * @param Order $order
     *
     * @return string
     * @throws Exception
     */
    public function getPaymentHtml($order)
    {
        return $this->paymentHelper->getInfoBlockHtml(
            $order->getPayment(),
            $this->getStoreId()
        );
    }

    /**
     * @return int
     * @throws NoSuchEntityException
     */
    public function getStoreId()
    {
        return $this->storeManager->getStore()->getId();
    }

    /**
     * @param StoreInterface $store
     *
     * @return StoreInterface
     */
    public function getStoreData($store)
    {
        $store->setData('base_url', $store->getBaseUrl());
        $store->setData('current_url', $store->getCurrentUrl());
        $store->setData('base_currency_code', $store->getBaseCurrencyCode());
        $store->setData('current_currency_code', $store->getCurrentCurrencyCode());
        $store->setData('current_currency_symbol', $this->_currency->getCurrencySymbol());

        return $store;
    }

    /**
     * @return array
     */
    public function getTemplateVars()
    {
        return $this->liquidFilters->addTemplateVars();
    }

    /**
     * @return string
     */
    public function getModifier()
    {
        return self::jsonEncode($this->liquidFilters->getFilters());
    }

    /**
     * @param $templateType
     * @param $templateId
     *
     * @return string
     * @throws FileSystemException
     */
    public function getDefaultTemplateHtml($templateType, $templateId)
    {
        return $this->readFile($this->getTemplatePath($templateType, $templateId));
    }

    /**
     * @param $relativePath
     *
     * @return string
     * @throws FileSystemException
     */
    public function readFile($relativePath)
    {
        $rootDirectory = $this->fileSystem->getDirectoryRead(DirectoryList::ROOT);

        return $rootDirectory->readFile($relativePath);
    }

    /**
     * Get default template path
     *
     * @param $templateType
     * @param $templateId
     * @param string $type
     *
     * @return string
     */
    public function getTemplatePath($templateType, $templateId, $type = '.html')
    {
        $DS = DIRECTORY_SEPARATOR;

        return $this->getBaseTemplatePath() . $templateType . $DS . $templateId . $type;
    }

    /**
     * Get base template path
     * @return string
     */
    public function getBaseTemplatePath()
    {
        // Get directory of Data.php
        $currentDir = __DIR__;

        // Get root directory(path of magento's project folder)
        $rootPath = $this->directoryList->getRoot();

        $currentDirArr = explode('\\', $currentDir);
        if (count($currentDirArr) === 1) {
            $currentDirArr = explode('/', $currentDir);
        }

        $rootPathArr = explode('/', $rootPath);
        if (count($rootPathArr) === 1) {
            $rootPathArr = explode('\\', $rootPath);
        }

        $basePath = '';
        for ($i = count($rootPathArr); $i < count($currentDirArr) - 1; $i++) {
            $basePath .= $currentDirArr[$i] . '/';
        }
        $DS   = DIRECTORY_SEPARATOR;
        $Path = $basePath . 'view' . $DS . 'base' . $DS . 'templates' . $DS . 'demo' . $DS;

        return $Path;
    }

    /**
     * @param $templateType
     * @param $templateId
     *
     * @return string
     * @throws FileSystemException
     */
    public function getDefaultTemplateCss($templateType, $templateId)
    {
        return $this->readFile($this->getTemplatePath($templateType, $templateId, '.css'));
    }

    /**
     * @param string $page
     * @param null $storeId
     * @param null $customerGroup
     *
     * @return string|string[]|null
     * @throws LocalizedException
     */
    public function getCouponCode($page, $storeId = null, $customerGroup = null)
    {
        $code     = '';
        $template = $this->getTemplateData($page, $storeId, $customerGroup);
        if ($template) {
            $config = $template->getData();
            if (!empty($config['rule_id'])) {
                $code = $this->generateCoupon->generateCoupon($config);
            }
        }

        return $code;
    }

    /**
     * @param $config
     * @param $couponCode
     *
     * @return array
     */
    public function getCouponInfo($config, $couponCode)
    {
        $coupon = [];
        if (!empty($config['rule_id']) && $this->generateCoupon->getRule($config['rule_id'])) {
            $rule           = $this->generateCoupon->getRule($config['rule_id']);
            $symbol         = $rule->getSimpleAction() === Rule::BY_PERCENT_ACTION ? '%' : '';
            $discountAmount = in_array($rule->getSimpleAction(), ['by_fixed', 'cart_fixed', 'buy_x_get_y'])
                ? $this->liquidFilters->money_with_currency($rule->getDiscountAmount())
                : (float) $rule->getDiscountAmount();

            $coupon = [
                'rule_id'         => $rule->getRuleId(),
                'description'     => $rule->getDescription(),
                'discount_amount' => $discountAmount . $symbol,
                'code'            => $couponCode
            ];
        }

        return $coupon;
    }

    /**
     * @return bool
     */
    public function hasHyvaTheme()
    {
        return $this->_moduleManager->isEnabled('Hyva_Theme');
    }
}

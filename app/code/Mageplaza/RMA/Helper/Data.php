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

namespace Mageplaza\RMA\Helper;

use DateTime;
use DateTimeZone;
use Exception;
use Magento\Authorization\Model\UserContextInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Helper\ImageFactory;
use Magento\Catalog\Model\Product;
use Magento\Customer\Api\GroupRepositoryInterface;
use Magento\Customer\Model\Customer;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Directory\Model\Currency\DefaultLocator;
use Magento\Directory\Model\CurrencyFactory;
use Magento\Framework\Api\Data\ImageContentInterface;
use Magento\Framework\Api\ImageContent;
use Magento\Framework\Api\ImageProcessorInterface;
use Magento\Framework\App\Area;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\Http\Context as HttpContext;
use Magento\Framework\DataObject;
use Magento\Framework\Escaper;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\MailException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\ValidatorException;
use Magento\Framework\File\Size;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Io\File;
use Magento\Framework\Locale\CurrencyInterface;
use Magento\Framework\Locale\Format;
use Magento\Framework\Locale\FormatInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Phrase;
use Magento\Framework\Stdlib\DateTime\DateTime as StdlibDateTime;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Item as OrderItem;
use Magento\Store\Api\StoreRepositoryInterface;
use Magento\Store\Api\WebsiteRepositoryInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Store\Model\System\Store as SystemStore;
use Magento\User\Model\UserFactory;
use Mageplaza\Core\Helper\AbstractData;
use Mageplaza\RMA\Helper\Image as HelperImage;
use Mageplaza\RMA\Mail\Template\TransportBuilder;
use Mageplaza\RMA\Model\Config\Source\System\Request\FieldType;
use Mageplaza\RMA\Model\Config\Source\System\Request\ReplyName;
use Mageplaza\RMA\Model\Request;
use Mageplaza\RMA\Model\Request\Item;
use Mageplaza\RMA\Model\Request\Reply;
use Mageplaza\RMA\Model\ResourceModel\Rule\Collection;
use Mageplaza\RMA\Model\ResourceModel\Rule\CollectionFactory;
use Mageplaza\RMA\Model\ResourceModel\ShippingLabel\Collection as ShippingLabelCol;
use Mageplaza\RMA\Model\ResourceModel\ShippingLabel\CollectionFactory as ShippingLabelColFact;
use Mageplaza\RMA\Model\Rule;
use Mageplaza\RMA\Model\ShippingLabel;

/**
 * Class Data
 * @package Mageplaza\RMA\Helper
 */
class Data extends AbstractData
{
    const CONFIG_MODULE_PATH = 'mprma';
    const XML_PATH_REQUEST   = 'request';

    /**
     * @var StdlibDateTime
     */
    protected $_dateTime;

    /**
     * @var ProductRepositoryInterface
     */
    protected $_productRepository;

    /**
     * @var SystemStore
     */
    protected $_systemStore;

    /**
     * @var Escaper
     */
    protected $_escape;

    /**
     * @var CustomerSession
     */
    protected $_customerSession;

    /**
     * @var HttpContext
     */
    protected $_httpContext;

    /**
     * @var DefaultLocator
     */
    protected $_currencyLocator;

    /**
     * @var CurrencyFactory
     */
    protected $_currencyFactory;

    /**
     * @var CurrencyInterface
     */
    protected $_localeCurrency;

    /**
     * @var Size
     */
    protected $_fileSize;

    /**
     * @var ImageFactory
     */
    protected $_imageHelperFactory;

    /**
     * @var FormatInterface|Format
     */
    protected $_priceFormat;

    /**
     * @var TransportBuilder
     */
    protected $_transportBuilder;

    /**
     * @var Filesystem
     */
    protected $_filesystem;

    /**
     * @var HelperImage
     */
    protected $_helperImage;

    /**
     * @var bool|Rule
     */
    protected $_requestRule;

    /**
     * @var CollectionFactory
     */
    protected $_ruleColFact;

    /**
     * @var ShippingLabelColFact
     */
    protected $_shippingLabelColFact;

    /**
     * @var UserContextInterface
     */
    protected $auth;

    /**
     * @var UserFactory
     */
    protected $userFactory;

    /**
     * @var Customer
     */
    protected $customer;

    /**
     * @var GroupRepositoryInterface
     */
    protected $groupRepository;

    /**
     * @var WebsiteRepositoryInterface
     */
    protected $websiteRepository;

    /**
     * @var StoreRepositoryInterface
     */
    protected $storeRepository;

    /**
     * @var ImageProcessorInterface
     */
    protected $imageProcessor;

    /**
     * @var FileProcessor
     */
    protected $fileProcessor;

    /**
     * @var File
     */
    protected $ioFile;

    /**
     * @var OrderInterface
     */
    protected $orderInterface;

    /**
     * Data constructor.
     *
     * @param Context $context
     * @param ObjectManagerInterface $objectManager
     * @param StoreManagerInterface $storeManager
     * @param StdlibDateTime $dateTime
     * @param ProductRepositoryInterface $productRepository
     * @param SystemStore $systemStore
     * @param Escaper $escape
     * @param CustomerSession $customerSession
     * @param HttpContext $httpContext
     * @param DefaultLocator $currencyLocator
     * @param CurrencyFactory $currencyFactory
     * @param CurrencyInterface $localeCurrency
     * @param Size $fileSize
     * @param ImageFactory $imageHelperFactory
     * @param FormatInterface $priceFormat
     * @param TransportBuilder $transportBuilder
     * @param Filesystem $filesystem
     * @param Image $helperImage
     * @param CollectionFactory $ruleColFact
     * @param ShippingLabelColFact $shippingLabelColFact
     * @param UserContextInterface $auth
     * @param UserFactory $userFactory
     * @param Customer $customer
     * @param GroupRepositoryInterface $groupRepository
     * @param WebsiteRepositoryInterface $websiteRepository
     * @param StoreRepositoryInterface $storeRepository
     * @param ImageProcessorInterface $imageProcessor
     * @param FileProcessor $fileProcessor
     * @param File $ioFile
     * @param OrderInterface $orderInterface
     */
    public function __construct(
        Context $context,
        ObjectManagerInterface $objectManager,
        StoreManagerInterface $storeManager,
        StdlibDateTime $dateTime,
        ProductRepositoryInterface $productRepository,
        SystemStore $systemStore,
        Escaper $escape,
        CustomerSession $customerSession,
        HttpContext $httpContext,
        DefaultLocator $currencyLocator,
        CurrencyFactory $currencyFactory,
        CurrencyInterface $localeCurrency,
        Size $fileSize,
        ImageFactory $imageHelperFactory,
        FormatInterface $priceFormat,
        TransportBuilder $transportBuilder,
        Filesystem $filesystem,
        HelperImage $helperImage,
        CollectionFactory $ruleColFact,
        ShippingLabelColFact $shippingLabelColFact,
        UserContextInterface $auth,
        UserFactory $userFactory,
        Customer $customer,
        GroupRepositoryInterface $groupRepository,
        WebsiteRepositoryInterface $websiteRepository,
        StoreRepositoryInterface $storeRepository,
        ImageProcessorInterface $imageProcessor,
        FileProcessor $fileProcessor,
        File $ioFile,
        OrderInterface $orderInterface
    ) {
        $this->_dateTime             = $dateTime;
        $this->_productRepository    = $productRepository;
        $this->_systemStore          = $systemStore;
        $this->_escape               = $escape;
        $this->_customerSession      = $customerSession;
        $this->_httpContext          = $httpContext;
        $this->_currencyLocator      = $currencyLocator;
        $this->_currencyFactory      = $currencyFactory;
        $this->_localeCurrency       = $localeCurrency;
        $this->_fileSize             = $fileSize;
        $this->_imageHelperFactory   = $imageHelperFactory;
        $this->_priceFormat          = $priceFormat;
        $this->_transportBuilder     = $transportBuilder;
        $this->_filesystem           = $filesystem;
        $this->_helperImage          = $helperImage;
        $this->_ruleColFact          = $ruleColFact;
        $this->_shippingLabelColFact = $shippingLabelColFact;
        $this->auth                  = $auth;
        $this->userFactory           = $userFactory;
        $this->customer              = $customer;
        $this->groupRepository       = $groupRepository;
        $this->websiteRepository     = $websiteRepository;
        $this->storeRepository       = $storeRepository;
        $this->imageProcessor        = $imageProcessor;
        $this->fileProcessor         = $fileProcessor;
        $this->ioFile                = $ioFile;
        $this->orderInterface        = $orderInterface;

        parent::__construct($context, $objectManager, $storeManager);
    }

    /**
     * @param string $code
     * @param null $storeId
     *
     * @return string
     */
    public function getRequestConfig($code = '', $storeId = null)
    {
        $code = ($code !== '') ? '/' . $code : '';

        return $this->getModuleConfig(self::XML_PATH_REQUEST . $code, $storeId);
    }

    /**
     * @param string $code
     * @param null $storeId
     *
     * @return mixed
     */
    public function getEmailConfig($code = '', $storeId = null)
    {
        $code = ($code !== '') ? '/' . $code : '';

        return $this->getModuleConfig('email' . $code, $storeId);
    }

    /**
     * @return bool
     */
    public function isDefaultConfigPage()
    {
        $params = $this->_request->getParams();

        return !(isset($params['store']) || isset($params['website']));
    }

    /**
     * @param string $date
     *
     * @return string
     */
    public function getTimeAgo($date)
    {
        $timestamp   = strtotime($date);
        $currentTime = strtotime($this->getCurrentDate());
        if ($currentTime <= $timestamp) {
            return __('just now');
        }

        $diff = $currentTime - $timestamp;
        if ($diff <= 30) {
            return __('in a few seconds');
        }

        $timeArr = ['minute' => 60, 'hour' => 3600, 'day' => 86400, 'month' => 2592000, 'year' => 31104000];

        if ($diff >= $timeArr['year']) {
            $diff = round($diff / $timeArr['year']);
            $html = ($diff >= 2) ? __('%1 years ago', $diff) : __('%1 year ago', $diff);
        } elseif ($diff >= $timeArr['month']) {
            $diff = round($diff / $timeArr['month']);
            $html = ($diff >= 2) ? __('%1 months ago', $diff) : __('%1 month ago', $diff);
        } elseif ($diff >= $timeArr['day']) {
            $diff = round($diff / $timeArr['day']);
            $html = ($diff >= 2) ? __('%1 days ago', $diff) : __('%1 day ago', $diff);
        } elseif ($diff >= $timeArr['hour']) {
            $diff = round($diff / $timeArr['hour']);
            $html = ($diff >= 2) ? __('%1 hours ago', $diff) : __('%1 hour ago', $diff);
        } elseif ($diff >= $timeArr['minute']) {
            $diff = round($diff / $timeArr['minute']);
            $html = ($diff >= 2) ? __('%1 minutes ago', $diff) : __('%1 minute ago', $diff);
        } else {
            $html = __('%1 seconds ago', $diff);
        }

        return $html;
    }

    /**
     * @return string
     */
    public function getCurrentDate()
    {
        return $this->_dateTime->date();
    }

    /**
     * Get the rule that is validated with this product
     *
     * @param Product|ProductInterface $product
     * @param Order $order
     *
     * @return mixed|null
     */
    public function validateProductInRule($product, $order)
    {
        $this->_requestRule = false;
        /** if the product use the current rules */
        $ruleCollection = $this->getRuleCollection($order);
        foreach ($ruleCollection as $rule) {
            /** @var Rule $rule */
            if ($rule->getConditions()->validate($product)) {
                $this->_requestRule = $rule;
                break;
            }
        }

        return $this->_requestRule;
    }

    /**
     * @param string|int $productId
     * @param Order $order
     *
     * @return bool|mixed|null
     */
    public function canReturnProduct($productId, $order)
    {
        $product = $this->getProductById($productId);
        if (!$product) {
            return true;
        }

        return $this->validateProductInRule($product, $order);
    }

    /**
     * @param Order $order
     *
     * @return bool
     */
    public function canReturnAllProducts($order)
    {
        $orderItems = $order->getItemsCollection();
        $canReturn  = false;
        foreach ($orderItems as $item) {
            /** @var OrderItem $item */
            if ($this->canReturnProduct($item->getProductId(), $order)) {
                $canReturn = true;
                break;
            }
        }

        return $canReturn;
    }

    /**
     * Get rule collection
     *
     * @param Order $order
     *
     * @return Collection
     */
    public function getRuleCollection($order)
    {
        /** @var Collection $collection */
        $collection = $this->_ruleColFact->create()
            ->addFieldToFilter('status', 1)
            ->addFieldToFilter('websites', [
                ['finset' => $order->getStore()->getWebsiteId()]
            ])
            ->addFieldToFilter('customer_group', [
                ['finset' => $order->getCustomerGroupId() ?: 0]
            ])
            ->setOrder('priority', 'asc');

        return $collection;
    }

    /**
     * @param int $productId
     *
     * @return bool|ProductInterface|Product
     */
    public function getProductById($productId)
    {
        try {
            $product = $this->_productRepository->getById($productId);
        } catch (Exception $e) {
            return false;
        }

        return $product;
    }

    /**
     * @param string $productId
     * @param Order $order
     *
     * @return array
     */
    public function getValidatedReasons($productId, $order)
    {
        $product    = $this->getProductById($productId);
        $allReasons = $this->getReasonOptionArray();
        unset($allReasons[0]);
        if (!$product) {
            return $allReasons;
        }
        if ($rule = $this->validateProductInRule($product, $order)) {
            $validReasons = explode(',', $rule->getReason() ?: '');
            foreach ($allReasons as $key => $reason) {
                if (!in_array($reason['value'], $validReasons, true)) {
                    unset($allReasons[$key]);
                }
            }

            return $allReasons;
        }

        return [];
    }

    /**
     * @param string $productId
     * @param Order $order
     *
     * @return array
     */
    public function getValidatedSolutions($productId, $order)
    {
        $product      = $this->getProductById($productId);
        $allSolutions = $this->getSolutionOptionArray();
        unset($allSolutions[0]);
        if (!$product) {
            return $allSolutions;
        }
        if ($rule = $this->validateProductInRule($product, $order)) {
            $validSolutions = explode(',', $rule->getSolution() ?: '');
            foreach ($allSolutions as $key => $solution) {
                if (!in_array($solution['value'], $validSolutions, true)) {
                    unset($allSolutions[$key]);
                }
            }

            return $allSolutions;
        }

        return [];
    }

    /**
     * @param string $productId
     * @param Order $order
     *
     * @return array
     */
    public function getValidatedAdditionalFields($productId, $order)
    {
        $product   = $this->getProductById($productId);
        $allFields = $this->getAdditionalFieldOptionArray();
        unset($allFields[0]);
        if (!$product) {
            return $allFields;
        }
        if ($rule = $this->validateProductInRule($product, $order)) {
            $validFields = explode(',', $rule->getAdditionalField() ?: '');
            foreach ($allFields as $key => $field) {
                if (!in_array($field['value'], $validFields, true)) {
                    unset($allFields[$key]);
                }
            }

            return $allFields;
        }

        return [];
    }

    /**
     * @param Order $order
     *
     * @return ShippingLabelCol
     */
    public function getShippingLabels($order)
    {
        /** @var ShippingLabelCol $shippingLabelCol */
        $shippingLabelCol = $this->_shippingLabelColFact->create();
        $shippingLabelCol->addFieldToFilter('status', 1)
            ->addFieldToFilter('store_id', [
                ['finset' => Store::DEFAULT_STORE_ID],
                ['finset' => $order->getStoreId()]
            ])
            ->setOrder('priority', 'asc');

        return $shippingLabelCol;
    }

    /**
     * @param Order $order
     *
     * @return ShippingLabel|DataObject
     */
    public function getDefaultShippingLabel($order)
    {
        $allShippingLabels = $this->getShippingLabels($order);
        foreach ($allShippingLabels as $key => $shippingLabel) {
            /** @var ShippingLabel $shippingLabel */
            if (!$shippingLabel->getConditions()->validate($order)) {
                $allShippingLabels->removeItemByKey($key);
            }
        }

        return $allShippingLabels->getFirstItem();
    }

    /**
     * @param Order $order
     *
     * @return array
     */
    public function getValidatedShippingLabels($order)
    {
        $options     = [];
        $emptyOption = [
            'value' => 0,
            'label' => __('-- Please select --')
        ];
        foreach ($this->getShippingLabels($order) as $shippingLabel) {
            if ($shippingLabel->getConditions()->validate($order)) {
                /** @var ShippingLabel $shippingLabel */
                $options[] = [
                    'value' => $shippingLabel->getId(),
                    'label' => $shippingLabel->getLabel()
                ];
            }
        }
        array_unshift($options, $emptyOption);

        return $options;
    }

    /**
     * @param array $field
     * @param Item $item
     * @param bool $isNew
     *
     * @return string
     */
    public function getValidatedAdditionalFieldHtml($field, $item, $isNew)
    {
        $requiredClass = $field['is_require'] ? 'required-entry' : '';
        $fieldContent  = $isNew ? '' : $field['content'];
        $dataValidate = '';
        if ($field['is_require'] && $field['value']) {
            $dataValidate  = 'data-validate="{&quot;required&quot;: true,&quot;no-only-whitespace&quot;: true,&quot;'. $field['validation'] .'&quot;: true}"';
        } elseif ($field['is_require']) {
            $dataValidate  = 'data-validate="{&quot;required&quot;: true,&quot;no-only-whitespace&quot;: true';
        } elseif ($field['validation']) {
            $dataValidate  = 'data-validate="{&quot;'. $field['validation'] .'&quot;: true}"';
        }

        $html = '<label class="label admin__field-label" for="' . $item->getId() . '_' . $field['value'] . '">
        <span class="' . $requiredClass . '">' . $field['label'] . '</span></label>';
        switch ($field['type']) {
            case FieldType::TEXT:
                $html .= '<input id="' . $item->getId() . '_' . $field['value'] . '"
                           type="text"
                           class="admin__control-text ' . $field['validation'] . ' ' . $requiredClass . '"
                           '. $dataValidate .'
                           name="request[products][' . $item->getId() . '][additional_fields][' . $field['value'] . ']"
                           value="' . $fieldContent . '"
                           disabled>';
                break;
            case FieldType::TEXT_AREA:
                $html .= '<textarea id="' . $item->getId() . '_' . $field['value'] . '"
                              title="' . $field['label'] . '"
                              rows="2"
                              cols="15"
                              '. $dataValidate .'
                              name="request[products][' . $item->getId() . '][additional_fields][' . $field['value'] . ']"
                              class="textarea admin__control-textarea '
                    . $field['validation'] . ' ' . $requiredClass . '"
                              disabled
                              spellcheck="false">' . $fieldContent . '</textarea>';
                break;
        }

        return $html;
    }

    /**
     * @param array $field
     *
     * @return string
     */
    public function getAdditionalFieldHtml($field)
    {
        $requiredClass = $field['is_require'] ? 'required-entry' : '';
        $dataValidate = '';
        if ($field['is_require'] && $field['value']) {
            $dataValidate  = 'data-validate="{&quot;required&quot;: true,&quot;no-only-whitespace&quot;: true,&quot;'. $field['validation'] .'&quot;: true}"';
        } elseif ($field['is_require']) {
            $dataValidate  = 'data-validate="{&quot;required&quot;: true,&quot;no-only-whitespace&quot;: true';
        } elseif ($field['validation']) {
            $dataValidate  = 'data-validate="{&quot;'. $field['validation'] .'&quot;: true}"';
        }

        $html = '<label class="label admin__field-label" for="' . '_' . $field['value'] . '">
        <span class="' . $requiredClass . '">' . $field['label'] . '</span></label>';
        switch ($field['type']) {
            case FieldType::TEXT:
                $html .= '<input id="' . '_' . $field['value'] . '"
                           type="text"
                           class="admin__control-text ' . $field['validation'] . ' ' . $requiredClass . ' w-full"
                           name="request[all_products][additional_fields][' . $field['value'] . ']"
                           value=""
                           ' . $dataValidate . '>';
                break;
            case FieldType::TEXT_AREA:
                $html .= '<textarea id="' . '_' . $field['value'] . '"
                              title="' . $field['label'] . '"
                              rows="2"
                              cols="15"
                              name="request[all_products][additional_fields][' . $field['value'] . ']"
                              class="textarea admin__control-textarea '
                    . $field['validation'] . ' ' . $requiredClass . '"
                              spellcheck="false"></textarea>';
                break;
        }

        return $html;
    }

    /**
     * @return array
     */
    public function getReasonOptionArray()
    {
        $options = [];
        $reasons = self::jsonDecode($this->getRequestConfig('rma/reason'));
        if (count($reasons)) {
            $emptyOption = [
                'value' => 0,
                'label' => __('-- Please select --')
            ];
            /** @var array[] $reasons */
            foreach ($reasons['name'] as $value => $label) {
                $options[] = compact('value', 'label');
            }
            array_unshift($options, $emptyOption);
        }

        return $options;
    }

    /**
     * @return array
     */
    public function getSolutionOptionArray()
    {
        $options   = [];
        $solutions = self::jsonDecode($this->getRequestConfig('rma/solution'));

        if (count($solutions)) {
            $emptyOption = [
                'value' => 0,
                'label' => __('-- Please select --')
            ];
            /** @var array[] $solutions */
            foreach ($solutions['name'] as $value => $label) {
                $options[] = [
                    'value' => $value,
                    'label' => $label
                ];
            }
            array_unshift($options, $emptyOption);
        }

        return $options;
    }

    /**
     * @return array
     */
    public function getAdditionalFieldOptionArray()
    {
        $options = [];
        $fields  = self::jsonDecode($this->getRequestConfig('rma/additional_field'));
        if (count($fields)) {
            $emptyOption = [
                'value' => 0,
                'label' => __('-- Please select --')
            ];
            $sortOrder   = array_column($fields['name'], 'sort');
            array_multisort($sortOrder, SORT_ASC, $fields['name']);
            /** @var array[] $fields */
            foreach ($fields['name'] as $value => $label) {
                $options[] = [
                    'value'      => $value,
                    'label'      => $label['title'],
                    'type'       => $label['type'],
                    'is_require' => $label['is_require'],
                    'validation' => $label['validation'],
                    'sort'       => $label['sort'],
                ];
            }
            array_unshift($options, $emptyOption);
        }

        return $options;
    }

    /**
     * @param array $origStores
     *
     * @return string
     */
    public function getStoresStructureHtml($origStores)
    {
        $data    = $this->_systemStore->getStoresStructure(false, $origStores);
        $content = '';
        foreach ($data as $website) {
            /** @var array[] $website */
            $content .= '<b>' . $website['label'] . '</b><br/>';
            foreach ($website['children'] as $group) {
                /** @var array[] $group */
                $content .= str_repeat('&nbsp;', 3) . '<b>' . $this->_escape->escapeHtml($group['label']) . '</b><br/>';
                foreach ($group['children'] as $store) {
                    $content .= str_repeat('&nbsp;', 6) . $this->_escape->escapeHtml($store['label']) . '<br/>';
                }
            }
        }

        return $content;
    }

    /**
     * @return int|null
     */
    public function getCustomerId()
    {
        return $this->_customerSession->getCustomerId();
    }

    /**
     * Check customer is logged in or not
     *
     * @return bool
     */
    public function isLoggedIn()
    {
        return $this->_request->isAjax() ? $this->_customerSession->isLoggedIn()
            : (bool) $this->_httpContext->getValue(\Magento\Customer\Model\Context::CONTEXT_AUTH);
    }

    /**
     * @param $price
     *
     * @return string
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @throws \Magento\Framework\Currency\Exception\CurrencyException
     */
    public function renderPriceCol($price)
    {
        $currencyCode = $this->storeManager->getStore()->getCurrentCurrency()->getCode();

        $rate = $this->_currencyFactory->create()->load($currencyCode)->getRate($currencyCode);
        $data = (float) $price * $rate;
        $data = $this->_localeCurrency->getCurrency($currencyCode)->toCurrency($data);

        return $data;
    }

    /**
     * @param $price
     * @param $orderId
     *
     * @return string
     * @throws \Magento\Framework\Currency\Exception\CurrencyException
     */
    public function renderPriceColByOrder($price, $orderId)
    {
        $orderInterface = $this->orderInterface;
        $order          = $orderInterface->loadByIncrementId($orderId);
        $currencyCode   = $order->getData('order_currency_code');

        $rate = $this->_currencyFactory->create()->load($currencyCode)->getRate($currencyCode);
        $data = (float) $price * $rate;
        $data = $this->_localeCurrency->getCurrency($currencyCode)->toCurrency($data);

        return $data;
    }

    /**
     * @return string
     */
    public function getJsonPriceFormat()
    {
        $code = $this->_currencyLocator->getDefaultCurrency($this->_request);

        return self::jsonEncode($this->_priceFormat->getPriceFormat(null, $code));
    }

    /**
     * @param string $productId
     *
     * @return string
     */
    public function getProductImgUrl($productId)
    {
        $product     = $this->getProductById($productId);
        $imageHelper = $this->_imageHelperFactory->create();
        $imageUrl    = $product
            ? $imageHelper->init($product, 'product_page_image_large')->setImageFile($product->getImage())->getUrl()
            : $imageHelper->getDefaultPlaceholderUrl('image');

        return str_replace('\\', '/', $imageUrl);
    }

    /**
     * @param OrderItem|Item|DataObject $item
     *
     * @return float|int
     */
    public function getAvailableQtyToReturn($item)
    {
        return $item->getQtyOrdered() * 1 - $item->getQtyRefunded() * 1 - $item->getMpQtyRma() * 1;
    }

    /**
     * @param string $date
     *
     * @return DateTime
     * @throws Exception
     */
    public function getConvertedDate($date)
    {
        $dateTime = new DateTime($date, new DateTimeZone('UTC'));
        $dateTime->setTimezone(new DateTimeZone($this->getTimezone()));

        return $dateTime;
    }

    /**
     * get configuration zone
     * @return mixed
     */
    public function getTimezone()
    {
        return $this->getConfigValue('general/locale/timezone');
    }

    /**
     * @param string|int $size
     *
     * @return string
     */
    public function fileSizeFormat($size)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
        $power = $size > 0 ? floor(log($size, 1024)) : 0;

        return number_format($size / (1024 ** $power), 2) . ' ' . $units[(int) $power];
    }

    /**
     * @return float
     */
    public function getMaxFileSize()
    {
        return $this->_fileSize->getMaxFileSizeInMb();
    }

    /**
     * @param int $position
     *
     * @return bool|mixed
     */
    public function getPolicyLink($position)
    {
        $policy          = $this->getConfigGeneral('policy');
        $policyLocations = $this->getConfigGeneral('policy_location');
        $policyLocations = array_map('intval', explode(',', $policyLocations));
        if (!$policy || !in_array($position, $policyLocations, true)) {
            return false;
        }

        return $this->_urlBuilder->getUrl($policy);
    }

    /**
     * @param Reply|Request $reply
     * @param array $emailInfo
     * @param array $vars
     *
     * @throws LocalizedException
     * @throws MailException
     * @throws FileSystemException
     * @throws ValidatorException
     */
    public function sendMail($reply, $emailInfo, $vars)
    {
        if (isset($emailInfo['to_email']) && $emailInfo['to_email']) {
            /** @var TransportBuilder $transport */
            $transport = $this->_transportBuilder
                ->setTemplateIdentifier($emailInfo['email_template'])
                ->setTemplateOptions([
                    'area'  => Area::AREA_FRONTEND,
                    'store' => $emailInfo['current_store_id']
                ])
                ->setFrom($emailInfo['sender'])
                ->addTo($emailInfo['to_email'])
                ->setTemplateVars($vars);
            $files     = self::jsonDecode($reply->getFiles());

            if ($files) {
                /** @var array $file */
                foreach ($files as $file) {
                    $mediaPath = $this->_filesystem->getDirectoryRead(DirectoryList::MEDIA)->getAbsolutePath();
                    $filePath  = $mediaPath . $this->_helperImage->getMediaPath($file['file']);
                    $transport->addAttachment($filePath, $file);
                }
            }
            $transport->getTransport()->sendMessage();
        }
    }

    /**
     * @param $request
     * @return array
     */
    public function getRequestStatusActions($request)
    {
        $statusActions = $request->getStatus()->getAllowAction();

        if(!isset($statusActions)){
            return [];
        }
        return array_map('intval', explode(',', $statusActions));
    }

    /**
     * @return array
     */
    public function getAllowedFileExtensions()
    {
        return array_map('trim', explode(',', $this->getRequestConfig('file_extensions')));
    }

    /**
     * @return string
     */
    public function getReplyAgentName()
    {
        if ((int) $this->getRequestConfig('reply_name') === ReplyName::DEFAULT_NAME) {
            return $this->getRequestConfig('default_name');
        }

        $user = $this->userFactory->create()->load($this->auth->getUserId());

        return $user->getname();
    }

    /**
     * @param string $customerId
     *
     * @return Customer
     */
    public function getCustomerName($customerId)
    {
        return $this->customer->load($customerId);
    }

    /**
     * @param int $data
     * @param string $type
     *
     * @throws InputException
     */
    public function checkYesNo($data, $type)
    {
        if (!in_array($data, [0, 1], true)) {
            throw new InputException(__('%1 should be 0 or 1.', $type));
        }
    }

    /**
     * @param int $data
     *
     * @throws InputException
     */
    public function checkIsInt($data)
    {
        if ($data < 0) {
            throw new InputException(__('priority should be greater than 0.'));
        }
    }

    /**
     * @param string $data
     *
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function checkCustomerGroup($data)
    {
        $arr = explode(',', $data);

        foreach ($arr as $item) {
            $this->groupRepository->getById($item);
        }
    }

    /**
     * @param string $data
     *
     * @throws NoSuchEntityException
     */
    public function checkWebsites($data)
    {
        $arr = explode(',', $data);

        foreach ($arr as $item) {
            $this->websiteRepository->getById($item);
        }
    }

    /**
     * @param string $data
     * @param array $info
     * @param string $type
     *
     * @throws InputException
     */
    public function checkRuleInfo($data, $info, $type)
    {
        $arr  = explode(',', $data);
        $list = [];

        foreach ($info as $value) {
            $list[] = $value['value'];
        }

        foreach ($arr as $item) {
            if ($item === '0') {
                continue;
            }

            if (!in_array($item, $list, true)) {
                throw new InputException(__('%1 is invalid.', $type));
            }
        }
    }

    /**
     * @param string $data
     *
     * @throws NoSuchEntityException
     */
    public function checkStore($data)
    {
        $stores = explode(',', $data);

        foreach ($stores as $item) {
            if ($item === '0') {
                continue;
            }

            $this->storeRepository->getById($item);
        }
    }

    /**
     * @param array $data
     *
     * @throws NoSuchEntityException
     */
    public function checkStoreChildItem($data)
    {
        foreach ($data as $item) {
            $this->checkStore($item['store_id']);
        }
    }

    /**
     * @param string $data
     * @param array $source
     * @param string $type
     *
     * @throws InputException
     */
    public function checkConfigSource($data, $source, $type)
    {
        $items = explode(',', $data);

        foreach ($items as $item) {
            if (!isset($source[$item])) {
                throw new InputException(__('%1 is invalid.', $type));
            }
        }
    }

    /**
     * @param ImageContent|ImageContentInterface $fileData
     *
     * @return string|void
     * @throws InputException
     */
    public function uploadFile($fileData)
    {
        if (!$fileData) {
            return null;
        }

        $result = $this->imageProcessor->processImageContent(
            Image::TEMPLATE_MEDIA_TYPE_SHIPPING_LABEL_FULL_PATH,
            $fileData
        );

        return substr($result, 1);
    }

    /**
     * @param array $fileData
     * @param string $type
     *
     * @return string|null
     * @throws InputException
     * @throws LocalizedException
     */
    public function uploadMultiFiles($fileData, $type = 'request')
    {
        $result = [];
        $count  = 0;

        if (!is_array($fileData)) {
            throw new InputException(__('The upload file type should be an array'));
        }

        foreach ($fileData as $item) {
            if (!$item) {
                return null;
            }

            $this->checkValid($item);
            $result[] = $this->processFileData($count, $item, $type);
            $count++;
        }

        if ($result) {
            return self::jsonEncode($result);
        }

        return null;
    }

    /**
     * @param array|ImageContent $file
     *
     * @return bool
     * @throws InputException
     */
    public function checkValid($file)
    {
        $fileContent = base64_decode(
            $this->isApi($file) ? $file->getBase64EncodedData() : $file['base64_encoded_data'],
            true
        );
        $fileName    = $this->fileProcessor->getFileName($file);
        if (empty($fileContent)) {
            throw new InputException(new Phrase('The file content must be valid base64 encoded data.'));
        }

        if (strlen($fileContent) > 2000000) {
            throw new InputException(__('The file size cannot more than 2MB.'));
        }

        $extension = $this->ioFile->getPathInfo($fileName)['extension'];

        if (!in_array($extension, $this->getAllowedFileExtensions(), true)) {
            throw new InputException(__('The file type is not allow.'));
        }

        if (!$this->fileProcessor->isNameValid($fileName)) {
            throw new InputException(new Phrase('Provided file name contains forbidden characters.'));
        }

        return true;
    }

    /**
     * @param int $count
     * @param ImageContent|array $item
     * @param string $type
     *
     * @return array
     * @throws LocalizedException
     */
    public function processFileData($count, $item, $type)
    {
        $file  = $this->fileProcessor->processFileContent(Image::TEMPLATE_MEDIA_PATH, $item);
        $isApi = $this->isApi($item);

        if ($type === 'request') {
            return [
                'position' => $count,
                'file'     => $file['file_path'],
                'name'     => $isApi ? $item->getName() : $item['name'],
                'size'     => $file['size']
            ];
        }

        return [
            'file_id'  => $this->_dateTime->timestamp(),
            'file'     => $file['file_path'],
            'location' => Image::TEMPLATE_MEDIA_PATH . '/tmp' . $file['file_path'],
            'name'     => $isApi ? $item->getName() : $item['name'],
            'size'     => $file['size'],
            'type'     => $file['file_type']
        ];
    }

    /**
     * @param ImageContent|array $imageContent
     *
     * @return bool
     */
    public function isApi($imageContent)
    {
        return !is_array($imageContent);
    }

    /**
     * @param string $file
     * @param string|null $type
     *
     * @return string
     * @throws NoSuchEntityException
     */
    public function getFileUrl($file, $type = null)
    {
        $fileUrl = $this->_helperImage->getMediaPath($file, $type);

        return $this->_helperImage->getMediaUrl($fileUrl);
    }

    /**
     * @return string
     */
    public function isUploadFiles()
    {
        return $this->getRequestConfig('allow_attachment');
    }

    /**
     * @return string
     */
    public function isReturnEachItem()
    {
        return $this->getRequestConfig('each_item');
    }

    /**
     * @param string $id
     *
     * @return bool
     */
    public function isDefaultStatus($id)
    {
        return $this->getRequestConfig('default_status') === $id;
    }
}

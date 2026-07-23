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
 * @package     Mageplaza_AbandonedCart
 * @copyright   Copyright (c) Mageplaza (http://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\AbandonedCart\Helper;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Module\Manager;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item;
use Magento\Store\Model\StoreManagerInterface;
use Mageplaza\Core\Helper\AbstractData;
use Mageplaza\SmsNotification\Helper\Data as MpSmsHelper;

/**
 * Class Sms
 * @package Mageplaza\AbandonedCart\Helper
 */
class Sms extends AbstractData
{
    public const CONFIG_MODULE_PATH = 'abandonedcart';

    /**
     * @var EncryptorInterface
     */
    protected $encryptor;

    /**
     * @var CustomerRepositoryInterface
     */
    protected $customerRepository;

    /**
     * @var Manager
     */
    protected $moduleManager;

    /**
     * @var Data
     */
    protected $helperData;

    /**
     * @var PriceCurrencyInterface
     */
    protected $priceCurrency;

    /**
     * Sms constructor.
     *
     * @param Context $context
     * @param ObjectManagerInterface $objectManager
     * @param StoreManagerInterface $storeManager
     * @param EncryptorInterface $encryptor
     * @param CustomerRepositoryInterface $customerRepository
     * @param Data $helperData
     * @param PriceCurrencyInterface $priceCurrency
     * @param Manager $moduleManager
     */
    public function __construct(
        Context $context,
        ObjectManagerInterface $objectManager,
        StoreManagerInterface $storeManager,
        EncryptorInterface $encryptor,
        CustomerRepositoryInterface $customerRepository,
        Data $helperData,
        PriceCurrencyInterface $priceCurrency,
        Manager $moduleManager
    ) {
        $this->encryptor          = $encryptor;
        $this->customerRepository = $customerRepository;
        $this->moduleManager      = $moduleManager;
        $this->helperData         = $helperData;
        $this->priceCurrency      = $priceCurrency;

        parent::__construct($context, $objectManager, $storeManager);
    }

    /**
     * @param string $code
     * @param null $storeId
     *
     * @return array|mixed|string
     */
    public function getSmsConfig($code = '', $storeId = null)
    {
        $code  = ($code !== '') ? '/' . $code : '';
        $value = $this->getConfigValue(self::CONFIG_MODULE_PATH . '/sms_notification' . $code, $storeId);

        if ($code == '/twilio_token') {
            return $this->encryptor->decrypt($value);
        }

        return $value;
    }

    /**
     * @return float|int
     */
    public function getSendAfter()
    {
        return $this->getSmsConfig('send_after') * 60;
    }

    /**
     * @param Quote $quote
     *
     * @return array
     * @throws NoSuchEntityException
     */
    public function getSmsData($quote)
    {
        return [
            'customer_name'  => $this->getCustomerName($quote),
            'customer_email' => $quote->getCustomerEmail(),
            'store_name'     => $this->storeManager->getStore($quote->getStoreId())->getName(),
            'items'          => $this->getItems($quote, $quote->getStoreId()),
            'order_total'    => $this->formatPrice(
                $quote->getBaseSubtotal(),
                $quote->getStoreId(),
                $quote->getQuoteCurrencyCode()
            )
        ];
    }

    /**
     * @param Quote $quote
     *
     * @return string
     */
    protected function getCustomerName($quote)
    {
        $lastName   = $quote->getCustomerLastname() ?: '';
        $middleName = $quote->getCustomerMiddlename() ?: '';
        $firstName  = $quote->getCustomerFirstname() ?: '';

        return $lastName . ' ' . $middleName . ' ' . $firstName;
    }

    /**
     * Format price
     *
     * @param $price
     * @param null $storeId
     * @param null $currency
     *
     * @return float|string
     */
    protected function formatPrice($price, $storeId = null, $currency = null)
    {
        return $this->priceCurrency->convertAndFormat($price, false, 2, $storeId, $currency);
    }

    /**
     * Get abandoned cart url
     *
     * @param $quote
     * @param $token
     *
     * @return string
     * @throws NoSuchEntityException
     */
    public function getAbandonedCartUrl($quote, $token)
    {
        $store = $this->storeManager->getStore($quote->getStoreId());

        return $this->_urlBuilder->getUrl('abandonedcart/checkout/cart', [
            '_scope'  => $store,
            'id'      => $quote->getId(),
            'token'   => $token,
            '_nosid'  => true,
            '_query'  => $this->helperData->getUrlSuffix($store),
            '_secure' => $store->isUrlSecure()
        ]);
    }

    /**
     * @param Quote $quote
     * @param null $storeId
     *
     * @return string
     */
    public function getItems($quote, $storeId = null)
    {
        $itemsData     = [];
        $quoteCurrency = $quote->getQuoteCurrencyCode();

        foreach ($quote->getAllItems() as $item) {
            /** @var Item $item */
            if ($item->getParentItemId()) {
                continue;
            }

            $itemName  = 'Name: ' . $item->getName();
            $itemQty   = 'Quantity: ' . $item->getQty();
            $itemPrice = 'Price: ' . $this->formatPrice($item->getBasePrice(), $storeId, $quoteCurrency);

            $itemsData[] = $itemName . ' - ' . $itemQty . ' - ' . $itemPrice;
        }

        $itemsData = PHP_EOL . implode(PHP_EOL, $itemsData);

        return $itemsData;
    }

    /**
     * Generate message content
     *
     * @param $data
     * @param $message
     *
     * @return mixed|string|string[]|null
     */
    public function generateMessageContent($data, $message)
    {
        $patternString = '#\{\{[a-z_.]*\}\}#';
        if (preg_match($patternString, $message)) {
            $message = preg_replace_callback(
                $patternString,
                function ($param) use ($data) {
                    return $this->getAttributeValue(trim($param[0], '{}'), $data);
                },
                $message
            );
        }

        return $message;
    }

    /**
     * @param $attribute
     * @param $data
     *
     * @return mixed|string
     */
    public function getAttributeValue($attribute, $data)
    {
        $attributes = explode('.', $attribute);
        $i          = 0;

        $dataArray = $data;
        foreach ($attributes as $value) {
            $i++;
            if (isset($dataArray[$value]) && !($dataArray[$value] instanceof DataObject)) {
                if (!is_array($dataArray[$value]) && $i === count($attributes)) {
                    return $dataArray[$value];
                }
                if (is_array($dataArray[$value]) && $i < count($attributes)) {
                    $dataArray = $dataArray[$value];
                }
            }
        }

        return '';
    }

    /**
     * @return mixed|null
     */
    protected function getSmsNotificationHelper()
    {
        if ($this->moduleManager->isEnabled('Mageplaza_SmsNotification')) {
            return $this->objectManager->create(MpSmsHelper::class);
        }

        return false;
    }

    /**
     * @param $quote
     *
     * @return mixed
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function getRecipient($quote)
    {
        $customer          = $this->customerRepository->getById($quote->getCustomerId());
        $abandonCartNumber = $customer->getCustomAttribute('mp_abandoned_cart_phone') ?
            $customer->getCustomAttribute('mp_abandoned_cart_phone')->getValue() : null;

        if (!$this->getSmsConfig('enable_abandon_cart_number') || $abandonCartNumber == null) {
            return $quote->getBillingAddress() ? $quote->getBillingAddress()->getTelephone() : '';
        }

        return $abandonCartNumber;
    }

    /**
     * @param $customerId
     *
     * @return mixed|string
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function getCustomerAbandonedCartPhone($customerId)
    {
        $customer = $this->customerRepository->getById($customerId);

        return $customer->getCustomAttribute('mp_abandoned_cart_phone') ?
            $customer->getCustomAttribute('mp_abandoned_cart_phone')->getValue() : '';
    }

    /**
     * @param null $storeId
     *
     * @return array|mixed|string
     */
    public function getTwilioSID($storeId = null)
    {
        if ($this->getSmsNotificationHelper()) {
            $mpSmsHelper = $this->getSmsNotificationHelper();

            return $mpSmsHelper->getTwilioSID($storeId) ?: $this->getSmsConfig('twilio_sid', $storeId);
        }

        return $this->getSmsConfig('twilio_sid', $storeId);
    }

    /**
     * @param null $storeId
     *
     * @return array|mixed|string
     */
    public function getTwilioToken($storeId = null)
    {
        if ($this->getSmsNotificationHelper()) {
            $mpSmsHelper = $this->getSmsNotificationHelper();

            return $mpSmsHelper->getTwilioToken($storeId) ?: $this->getSmsConfig('twilio_token', $storeId);
        }

        return $this->getSmsConfig('twilio_token', $storeId);
    }

    /**
     * @param null $storeId
     *
     * @return array|mixed|string
     */
    public function getSenderPhoneNumber($storeId = null)
    {
        if ($this->getSmsNotificationHelper()) {
            $mpSmsHelper = $this->getSmsNotificationHelper();

            return $mpSmsHelper->getSenderPhoneNumber($storeId) ?: $this->getSmsConfig('admin_phone_number', $storeId);
        }

        return $this->getSmsConfig('admin_phone_number', $storeId);
    }
}

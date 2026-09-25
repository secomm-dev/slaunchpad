<?php

declare(strict_types=1);

namespace Tiktok\Tiktok\Model\Event\Context;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\HTTP\Header;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use Magento\Framework\Locale\Resolver;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * @SuppressWarnings(PHPMD.CookieAndSessionMisuse)
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class EventContext
{
    /**
     * Cookie TikTok Pixel ID
     */
    private const COOKIE_TTP = '_ttp';

    /**
     * Cookie TikTok Click ID
     */
    private const COOKIE_TTCLID = 'ttclid';

    /**
     * @var \Magento\Customer\Model\Session
     */
    private CustomerSession $customerSession;

    /**
     * @var \Magento\Framework\Stdlib\CookieManagerInterface
     */
    private CookieManagerInterface $cookieManager;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    private StoreManagerInterface $storeManager;

    /**
     * @var \Magento\Framework\HTTP\Header
     */
    private Header $httpHeader;

    /**
     * @var \Magento\Framework\Locale\Resolver
     */
    private Resolver $localeResolver;

    /**
     * @var null
     */
    private $defaultBillingAddress = null;

    /**
     * @var \Magento\Framework\App\RequestInterface
     */
    private RequestInterface $request;

    /**
     * @var \Magento\Framework\UrlInterface
     */
    private UrlInterface $urlBuilder;

    /**
     * @var \Magento\Framework\HTTP\PhpEnvironment\RemoteAddress
     */
    private RemoteAddress $remoteAddress;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    private CheckoutSession $checkoutSession;

    /**
     * Init dependencies
     *
     * @param \Magento\Customer\Model\Session $customerSession
     * @param CookieManagerInterface $cookieManager
     * @param StoreManagerInterface $storeManager
     * @param Resolver $localeResolver
     * @param Header $httpHeader
     * @param RequestInterface $request
     * @param UrlInterface $urlBuilder
     * @param RemoteAddress $remoteAddress
     * @param CheckoutSession $checkoutSession
     */
    public function __construct(
        CustomerSession $customerSession,
        CookieManagerInterface $cookieManager,
        StoreManagerInterface $storeManager,
        Resolver $localeResolver,
        Header $httpHeader,
        RequestInterface $request,
        UrlInterface $urlBuilder,
        RemoteAddress $remoteAddress,
        CheckoutSession $checkoutSession
    ) {
        $this->customerSession = $customerSession;
        $this->cookieManager = $cookieManager;
        $this->storeManager = $storeManager;
        $this->localeResolver = $localeResolver;
        $this->httpHeader = $httpHeader;

        $this->request = $request;
        $this->urlBuilder = $urlBuilder;
        $this->remoteAddress = $remoteAddress;
        $this->checkoutSession = $checkoutSession;
    }

    /**
     * Return current customer ID
     *
     * @return string
     */
    public function getUserId(): string
    {
        return (string)($this->customerSession->getCustomerId() ?? $this->customerSession->getSessionId());
    }

    /**
     * Return customer email
     *
     * @return string|null
     */
    public function getEmail(): ?string
    {
        $customer = $this->getCustomer();
        return $customer ? $customer->getEmail() : null;
    }

    /**
     * Retrieve customer model object
     *
     * @return \Magento\Customer\Api\Data\CustomerInterface|null
     */
    private function getCustomer()
    {
        return $this->customerSession->isLoggedIn() ? $this->customerSession->getCustomer()->getDataModel() : null;
    }

    /**
     * Retrieve customer address phone number
     *
     * @return string|null
     */
    public function getPhone(): ?string
    {
        $address = $this->getDefaultBillingAddress();
        return $address ? $address->getTelephone() : null;
    }

    /**
     * Retrieve customer default billing address
     *
     * @return \Magento\Customer\Api\Data\AddressInterface|null
     */
    private function getDefaultBillingAddress()
    {

        if ($this->defaultBillingAddress === null) {
            $customer = $this->getCustomer();
            if (!$customer) {
                return null;
            }

            $addresses = $customer->getAddresses();
            if (empty($addresses)) {
                return null;
            }

            $defaultBillingId = $customer->getDefaultBilling();
            if (!$defaultBillingId) {
                return null;
            }

            foreach ($addresses as $address) {
                if ($address->getId() == $defaultBillingId) {
                    $this->defaultBillingAddress = $address;
                    return $address;
                }
            }
        }
        return $this->defaultBillingAddress;
    }

    /**
     * Retrieve customer firstname
     *
     * @return string|null
     */
    public function getFirstName(): ?string
    {
        $customer = $this->getCustomer();
        return $customer ? $customer->getFirstname() : null;
    }

    /**
     * Retrieve customer lastname
     *
     * @return string|null
     */
    public function getLastName(): ?string
    {
        $customer = $this->getCustomer();
        return $customer ? $customer->getLastname() : null;
    }

    /**
     * Retrieve customer default billing address - city
     *
     * @return string|null
     */
    public function getCity(): ?string
    {
        $address = $this->getDefaultBillingAddress();
        return $address ? $address->getCity() : null;
    }

    /**
     * Retrieve customer default billing address - state
     *
     * @return string|null
     */
    public function getState(): ?string
    {
        $address = $this->getDefaultBillingAddress();
        return $address ? $address->getRegion()->getRegionCode() : null;
    }

    /**
     * Retrieve customer default billing address - country
     *
     * @return string|null
     */
    public function getCountry(): ?string
    {
        $address = $this->getDefaultBillingAddress();
        return $address ? $address->getCountryId() : null;
    }

    /**
     * Retrieve customer default billing address - country
     *
     * @return string|null
     */
    public function getZipCode(): ?string
    {
        $address = $this->getDefaultBillingAddress();
        return $address ? $address->getPostcode() : null;
    }

    /**
     * Retrieve current website ID
     *
     * @return int
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getWebsiteId(): int
    {
        return (int)$this->storeManager->getStore()->getWebsiteId();
    }

    /**
     * Retrieve current website ID
     *
     * @return string
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getCurrencyCode(): string
    {
        return $this->storeManager->getStore()->getCurrentCurrencyCode();
    }

    /**
     * Retrieve current url
     *
     * @return string
     */
    public function getCurrentUrl(): string
    {
        return $this->urlBuilder->getCurrentUrl();
    }

    /**
     * Retrieve HTTP REFERER
     *
     * @return string
     */
    public function getReferrerUrl(): string
    {
        return $this->httpHeader->getHttpReferer();
    }

    /**
     * Retrieve HTTP REFERER
     *
     * @return string
     */
    public function getUserAgent(): string
    {
        return $this->httpHeader->getHttpUserAgent();
    }

    /**
     * Retrieve Client Remote Address.
     *
     * @return string
     */
    public function getIp(): string
    {
        return $this->remoteAddress->getRemoteAddress();
    }

    /**
     * Retrieve tiktok pixel ID from a cookie.
     *
     * @return string|null
     */
    public function getTtpCookie(): ?string
    {
        return $this->cookieManager->getCookie(self::COOKIE_TTP);
    }

    /**
     * Retrieve tiktok click ID from a cookie.
     *
     * @return string|null
     */
    public function getTtclidCookie(): ?string
    {
        return $this->cookieManager->getCookie(self::COOKIE_TTCLID);
    }

    /**
     * Retrieve locale
     *
     * @return null
     */
    public function getLocale()
    {
        return null;
    }

    /**
     * Get checkout quote instance by current session
     *
     * @return \Magento\Quote\Api\Data\CartInterface|\Magento\Quote\Model\Quote
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getQuote()
    {
        return $this->checkoutSession->getQuote();
    }

    /**
     * Get order instance based on last order ID
     *
     * @return \Magento\Sales\Model\Order
     */
    public function getOrder()
    {
        return $this->checkoutSession->getLastRealOrder();
    }
}

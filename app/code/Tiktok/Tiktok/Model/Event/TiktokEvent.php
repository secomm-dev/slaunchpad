<?php

declare(strict_types=1);

namespace Tiktok\Tiktok\Model\Event;

use JsonSerializable;
use Magento\Catalog\Model\Product;
use Magento\Sales\Model\Order;
use Tiktok\Tiktok\Api\EventDataInterface;
use Tiktok\Tiktok\Model\Config\ScopeManager;
use Tiktok\Tiktok\Model\Event\Context\EventContext;
use Tiktok\Tiktok\Model\ResourceModel\Product\ParentProductProvider;
use Tiktok\Tiktok\Model\ScopeManagerBuilder;

/**
 * TikTok event data handler
 * @SuppressWarnings(PHPMD.CyclomaticComplexity)
 * @SuppressWarnings(PHPMD.NPathComplexity)
 * @SuppressWarnings(PHPMD.BooleanGetMethodName)
 */
class TiktokEvent implements EventDataInterface, JsonSerializable
{
    /**
     * Event Source
     */
    private const EVENT_SOURCE = 'web';

    /**
     * @var \Tiktok\Tiktok\Model\Event\Context\EventContext
     */
    private EventContext $eventContext;

    /**
     * @var \Tiktok\Tiktok\Model\ScopeManagerBuilder
     */
    private ScopeManagerBuilder $scopeManagerBuilder;

    /**
     * @var string|null
     */
    private ?string $eventName = null;

    /**
     * @var array
     */
    private array $contents = [];

    /**
     * @var array|null
     */
    private ?array $orderData = null;

    /**
     * @var int
     */
    private int $eventTime;

    /**
     * @var string|null
     */
    private ?string $eventId = null;

    /**
     * @var array|null
     */
    private ?array $userData = null;

    /**
     * @var array|null
     */
    private ?array $pageData = null;

    /**
     * @var \Tiktok\Tiktok\Model\Event\Publisher
     */
    private Publisher $publisher;

    /**
     * @var mixed
     */
    private mixed $orderId = null;

    /**
     * @var float
     */
    private float $itemsTotalValue = 0;

    /**
     * @var bool
     */
    private bool $isS2SEvent = false;

    /**
     * @var string
     */
    private string $contentType;

    /**
     * TiktokEvent constructor
     *
     * @param \Tiktok\Tiktok\Model\Event\Context\EventContext $eventContext
     * @param \Tiktok\Tiktok\Model\ScopeManagerBuilder $scopeManagerBuilder
     * @param \Tiktok\Tiktok\Model\Event\Publisher $publisher
     */
    public function __construct(
        EventContext $eventContext,
        ScopeManagerBuilder $scopeManagerBuilder,
        Publisher $publisher,
    ) {
        $this->eventContext = $eventContext;
        $this->scopeManagerBuilder = $scopeManagerBuilder;
        $this->eventTime = time();
        $this->publisher = $publisher;
    }

    /**
     * Set Order Data
     *
     * @param Order $order
     *
     * @return $this
     */
    public function setOrderData(Order $order): self
    {
        $incrementId = $order->getIncrementId();
        $businessId = $this->getScopeManager()->getExternalBusinessId();

        if ($incrementId && $businessId) {
            $this->orderData = [
                'order_id' => $incrementId,
                'shop_id' => $businessId];
        }
        return $this;
    }

    /**
     * Return scope manager
     *
     * @return ScopeManager
     */
    public function getScopeManager(): ScopeManager
    {
        static $scopeManager = null;
        if ($scopeManager === null) {
            $scopeManager = $this->scopeManagerBuilder->create((int)$this->eventContext->getWebsiteId());
        }
        return $scopeManager;
    }

    /**
     * Get website ID
     *
     * @return int
     */
    public function getWebsiteId(): int
    {
        return $this->eventContext->getWebsiteId();
    }

    /**
     * @inheritDoc
     */
    public function jsonSerialize(): array
    {
        return $this->getEvent();
    }

    /**
     * Return event
     *
     * @return array
     */
    public function getEvent(): array
    {
        $pixelCode = $this->getScopeManager()->getPixelCode();
        $event = ['website_id' => $this->getWebsiteId()];
        if (self::EVENT_SOURCE) {
            $event['event_source'] = self::EVENT_SOURCE;
        }
        if ($pixelCode) {
            $event['event_source_id'] = $pixelCode;
        }
        $event['partner_name'] = 'Magento';
        $dataElement = $this->getDataElement();
        if (!empty($dataElement)) {
            $event['data'] = $dataElement;
        }
        return $event;
    }

    /**
     * Retrieve data element
     *
     * @return array
     */
    public function getDataElement(): array
    {
        $properties = $this->buildProperties();
        $userData = $this->getUserDataElement();
        $pageData = $this->getPageData();

        $data = [
            'event' => $this->getEventName(),
            'event_time' => $this->eventTime,
            'event_id' => $this->getEventId()];

        if (!empty($userData)) {
            $data['user'] = $userData;
        }

        if (!empty($properties)) {
            $data['properties'] = $properties;
        }

        if (!empty($pageData)) {
            $data['page'] = $pageData;
        }

        return $data;
    }

    /**
     * Build properties
     *
     * @return array
     */
    private function buildProperties(): array
    {
        $properties = [];

        if (!empty($this->contents)) {
            $properties['contents'] = $this->contents;
            $properties['content_type'] = $this->contentType;
        }

        $currencyCode = $this->eventContext->getCurrencyCode();
        if ($currencyCode) {
            $properties['currency'] = $currencyCode;
        }

        if ($this->itemsTotalValue > 0) {
            $properties['value'] = $this->itemsTotalValue;
        }

        if ($this->orderId) {
            $properties['order_id'] = $this->orderId;
        }

        $businessId = $this->getScopeManager()->getExternalBusinessId();
        if ($businessId) {
            $properties['shop_id'] = $businessId;
        }

        return $properties;
    }

    /**
     * Return user data element
     *
     * @return array
     */
    public function getUserDataElement(): array
    {
        if ($this->userData === null) {
            $this->buildUserData();
        }
        $this->updatedUserDataKeys();
        return $this->userData ?? [];
    }

    /**
     * Build user data
     *
     * @return void
     */
    private function buildUserData(): void
    {
        $baseData = $this->buildBaseUserData();
        if ($this->getScopeManager()->isAdvancedUserTrackingEnabled()) {
            $advancedData = $this->buildAdvancedUserData();
            $this->userData = array_merge($baseData, $advancedData);
        } else {
            $this->userData = $baseData;
        }
    }

    /**
     * Build base user data
     *
     * @return array
     */
    private function buildBaseUserData(): array
    {
        $data = [];
        $ip = $this->eventContext->getIp();
        if ($ip) {
            $data['ip'] = $ip;
        }
        $userAgent = $this->eventContext->getUserAgent();
        if ($userAgent) {
            $data['user_agent'] = $userAgent;
        }
        $userId = $this->eventContext->getUserId();
        if ($userId) {
            $data['external_id'] = $this->hashUserValue($userId);
        }
        $ttpCookie = $this->eventContext->getTtpCookie();
        if ($ttpCookie) {
            $data['ttp'] = $ttpCookie;
        }
        $ttclidCookie = $this->eventContext->getTtclidCookie();
        if ($ttclidCookie) {
            $data['ttclid'] = $ttclidCookie;
        }
        return $data;
    }

    /**
     * Hash user value
     *
     * @param string|null $value
     *
     * @return string|null
     */
    private function hashUserValue(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        return hash('sha256', strtolower(trim($value)));
    }

    /**
     * Build Advanced user data
     *
     * @return array
     */
    private function buildAdvancedUserData(): array
    {
        $data = [];
        if ($email = $this->eventContext->getEmail()) {
            $data['email'] = $this->hashUserValue($email);
        }
        if ($phone = $this->eventContext->getPhone()) {
            $data['phone'] = $this->hashUserValue($phone);
        }
        if ($firstName = $this->eventContext->getFirstName()) {
            $data['first_name'] = $this->hashUserValue($firstName);
        }
        if ($lastName = $this->eventContext->getLastName()) {
            $data['last_name'] = $this->hashUserValue($lastName);
        }
        if ($city = $this->eventContext->getCity()) {
            $data['city'] = $this->formatLocationValue($city);
        }
        if ($state = $this->eventContext->getState()) {
            $data['state'] = $this->formatLocationValue($state);
        }
        if ($country = $this->eventContext->getCountry()) {
            $data['country'] = $this->formatLocationValue($country);
        }
        if ($zipCode = $this->eventContext->getZipCode()) {
            $data['zip_code'] = $this->hashUserValue($zipCode);
        }
        if ($locale = $this->eventContext->getLocale()) {
            $data['locale'] = $locale;
        }

        return $data;
    }

    /**
     * Format location value
     *
     * @param string|null $value
     *
     * @return string|null
     */
    private function formatLocationValue(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        return strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $value));
    }

    /**
     * Updated keys in userData for Pixel or S2S
     *
     * @return void
     */
    private function updatedUserDataKeys(): void
    {
        if ($this->getIsS2SEvent()) {
            $this->swapUserDataKeys('phone_number', 'phone');
        } else {
            $this->swapUserDataKeys('phone', 'phone_number');
        }
        $this->setIsS2SEvent(false);
    }

    /**
     * Get isS2SEvent flag
     *
     * @return bool
     */
    public function getIsS2SEvent(): bool
    {
        return $this->isS2SEvent;
    }

    /**
     * Set isS2SEvent flag
     *
     * @param bool $isS2SEvent
     * @return $this
     */
    public function setIsS2SEvent($isS2SEvent): self
    {
        $this->isS2SEvent = $isS2SEvent;
        return $this;
    }

    /**
     * Swap keys in userData
     *
     * @param string $from
     * @param string $to
     * @return void
     */
    private function swapUserDataKeys($from, $to): void
    {
        if (array_key_exists($from, $this->userData)) {
            $this->userData[$to] = $this->userData[$from];
            unset($this->userData[$from]);
        }
    }

    /**
     * Return page data
     *
     * @return array
     */
    private function getPageData(): array
    {
        if ($this->pageData === null) {
            $this->pageData = [];

            $currentUrl = $this->eventContext->getCurrentUrl();
            if ($currentUrl) {
                $this->pageData['url'] = $currentUrl;
            }

            $referrerUrl = $this->eventContext->getReferrerUrl();
            if ($referrerUrl) {
                $this->pageData['referrer'] = $referrerUrl;
            }
        }

        return $this->pageData;
    }

    /**
     * Return event name
     *
     * @return string
     */
    public function getEventName(): string
    {
        return $this->eventName ?? '';
    }

    /**
     * Set event name
     *
     * @param string $eventName
     *
     * @return $this
     */
    public function setEventName(string $eventName): self
    {
        $this->eventName = $eventName;
        return $this;
    }

    /**
     * Return Event ID
     *
     * @return string
     */
    public function getEventId(): string
    {
        if ($this->eventId === null) {
            $prefix = 'event_' . $this->getEventName() . $this->eventContext->getUserId();
            $this->eventId = uniqid($prefix, true);
        }
        return $this->eventId;
    }

    /**
     * Set event ID
     *
     * @param string $eventId
     *
     * @return void
     */
    public function setEventId(string $eventId): void
    {
        $this->eventId = $eventId;
    }

    /**
     * Publish event to queue
     *
     * @return bool
     */
    public function publish(): bool
    {
        $this->publisher->publish($this);
        return true;
    }

    /**
     * Perform check if should report pixel tracking
     *
     * @return bool
     */
    public function shouldReport(): bool
    {
        return $this->getScopeManager()->isEnabled();
    }

    /**
     * Perform check if pixel tracking is enabled
     *
     * @return bool
     */
    public function isPixelTrackingEnabled(): bool
    {
        return (bool)$this->getScopeManager()->isPixelTrackingEnabled();
    }

    /**
     * Add quote items to event
     *
     * @return $this
     */
    public function addQuoteItemsToEvent(): self
    {
        foreach ($this->eventContext->getQuote()->getAllVisibleItems() as $item) {
            $product = $item->getProduct();
            if ($item->getHasChildren()) {
                foreach ($item->getChildren() as $child) {
                    $childProduct = $child->getProduct();
                    $this->addProduct($childProduct, (int)$item->getQty());
                }
            } else {
                $this->addProduct($product, (int)$item->getQty());
            }
        }
        return $this;
    }

    /**
     * Add product
     *
     * @param Product $product
     * @param float $quantity
     *
     * @return $this
     */
    public function addProduct(Product $product, float $quantity = 1): self
    {
        $productData = [
            'price' => $product->getFinalPrice(),
            'content_id' => $this->getContentId($product),
            'content_name' => $product->getName(),
            'quantity' => $quantity
        ];

        $category = $this->getProductCategory($product);
        if ($category) {
            $productData['content_category'] = $category;
        }

        $brand = $product->getTiktokBrand();
        if ($brand) {
            $productData['brand'] = $brand;
        }

        $this->contents[] = $productData;
        $this->contentType = ($product->getTypeId() === 'configurable' && $this->getEventName() === 'ViewContent')
            ? 'product_group'
            : 'product';
        $this->itemsTotalValue += ($product->getFinalPrice() * $quantity);

        return $this;
    }

    /**
     * Retrieve content_id
     *
     * @param Product $product
     * @return string
     */
    private function getContentId(Product $product): string
    {
        if ($product->getTypeId() === 'configurable' && !empty($product->getTiktokProductGroupId())) {
            return $product->getTiktokProductGroupId();
        }
        return $product->getSku();
    }

    /**
     * Return product category
     *
     * @param Product $product
     *
     * @return string|null
     */
    private function getProductCategory(Product $product): ?string
    {
        $category = $product->getCategoryCollection()->clear()->addAttributeToSelect('name')->load()->getFirstItem()
            ->getName();

        return $category ?: null;
    }

    /**
     * Add order to event
     *
     * @return $this
     */
    public function addOrderToEvent(): self
    {
        $order = $this->eventContext->getOrder();
        foreach ($order->getAllItems() as $item) {
            if ($item->getProductType() === 'simple' || $item->getProductType() === 'grouped') {
                $this->addProduct($item->getProduct(), (int)$item->getQtyOrdered());
            }
        }
        $this->orderId = $order->getIncrementId();
        return $this;
    }
}

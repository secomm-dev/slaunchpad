<?php
declare(strict_types=1);

namespace Tiktok\Tiktok\Model;

use Tiktok\Tiktok\Model\EventTypes;
use JsonException;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\HTTP\Client\Curl;

/**
 * Class TikTokEventService
 * Handles sending events to TikTok API
 */
class TikTokEventService
{
    /**
     * @var \Magento\Framework\HTTP\Client\Curl
     */
    protected Curl $httpClient;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected ScopeConfigInterface $config;

    /**
     * @var string
     */
    protected string $url;

    /**
     * Init dependencies
     *
     * @param Curl $httpClient
     * @param ScopeConfigInterface $config
     * @param string $url
     */
    public function __construct(
        Curl $httpClient,
        ScopeConfigInterface $config,
        string $url
    ) {
        $this->httpClient = $httpClient;
        $this->config = $config;
        $this->url = $url;
    }

    /**
     * Send page view event
     *
     * @param array $user
     * @param array $page
     * @param string $eventId
     * @param int $eventTime
     *
     * @return string
     * @throws JsonException
     */
    public function sendPageviewEvent(array $user, array $page, string $eventId, int $eventTime): string
    {
        $eventData = [
            'event' => EventTypes::PAGEVIEW,
            'event_time' => $eventTime,
            'event_id' => $eventId,
            'user' => $user,
            'page' => $page,];

        return $this->sendEvent(EventTypes::PAGEVIEW, $eventData);
    }

    /**
     * Send view content event
     *
     * @param array $user
     * @param array $page
     * @param array $properties
     * @param string $eventId
     * @param int $eventTime
     *
     * @return string
     * @throws JsonException
     */
    public function sendViewContentEvent(
        array $user,
        array $page,
        array $properties,
        string $eventId,
        int $eventTime
    ): string {
        $eventData = [
            'event' => EventTypes::VIEW_CONTENT,
            'event_time' => $eventTime,
            'event_id' => $eventId,
            'user' => $user,
            'page' => $page,
            'properties' => $properties,];

        return $this->sendEvent(EventTypes::VIEW_CONTENT, $eventData);
    }

    /**
     * Send add to cart event
     *
     * @param array $user
     * @param array $page
     * @param array $properties
     * @param string $eventId
     * @param int $eventTime
     *
     * @return string
     * @throws JsonException
     */
    public function sendAddToCartEvent(
        array $user,
        array $page,
        array $properties,
        string $eventId,
        int $eventTime
    ): string {
        $eventData = [
            'event' => EventTypes::ADD_TO_CART,
            'event_time' => $eventTime,
            'event_id' => $eventId,
            'user' => $user,
            'page' => $page,
            'properties' => $properties,];

        return $this->sendEvent(EventTypes::ADD_TO_CART, $eventData);
    }

    /**
     * Send initiate checkout event
     *
     * @param array $user
     * @param array $page
     * @param array $properties
     * @param string $eventId
     * @param int $eventTime
     *
     * @return string
     * @throws JsonException
     */
    public function sendInitiateCheckoutEvent(
        array $user,
        array $page,
        array $properties,
        string $eventId,
        int $eventTime
    ): string {
        $eventData = [
            'event' => EventTypes::INITIATE_CHECKOUT,
            'event_time' => $eventTime,
            'event_id' => $eventId,
            'user' => $user,
            'page' => $page,
            'properties' => $properties,];

        return $this->sendEvent(EventTypes::INITIATE_CHECKOUT, $eventData);
    }

    /**
     * Send add payment info event
     *
     * @param array $user
     * @param array $page
     * @param array $properties
     * @param string $eventId
     * @param int $eventTime
     *
     * @return string
     * @throws JsonException
     */
    public function sendAddPaymentInfoEvent(
        array $user,
        array $page,
        array $properties,
        string $eventId,
        int $eventTime
    ): string {
        $eventData = [
            'event' => EventTypes::ADD_PAYMENT_INFO,
            'event_time' => $eventTime,
            'event_id' => $eventId,
            'user' => $user,
            'page' => $page,
            'properties' => $properties,];

        return $this->sendEvent(EventTypes::ADD_PAYMENT_INFO, $eventData);
    }

    /**
     * Send place an order event
     *
     * @param array $user
     * @param array $page
     * @param array $properties
     * @param string $eventId
     * @param int $eventTime
     *
     * @return string
     * @throws JsonException
     */
    public function sendPlaceAnOrderEvent(
        array $user,
        array $page,
        array $properties,
        string $eventId,
        int $eventTime
    ): string {
        $eventData = [
            'event' => EventTypes::PLACE_AN_ORDER,
            'event_time' => $eventTime,
            'event_id' => $eventId,
            'user' => $user,
            'page' => $page,
            'properties' => $properties,];

        return $this->sendEvent(EventTypes::PLACE_AN_ORDER, $eventData);
    }

    /**
     * Send complete payment event
     *
     * @param array $user
     * @param array $page
     * @param array $properties
     * @param string $eventId
     * @param int $eventTime
     *
     * @return string
     * @throws JsonException
     */
    public function sendCompletePaymentEvent(
        array $user,
        array $page,
        array $properties,
        string $eventId,
        int $eventTime
    ): string {
        $eventData = [
            'event' => EventTypes::COMPLETE_PAYMENT,
            'event_time' => $eventTime,
            'event_id' => $eventId,
            'user' => $user,
            'page' => $page,
            'properties' => $properties,];

        return $this->sendEvent(EventTypes::COMPLETE_PAYMENT, $eventData);
    }

    /**
     * Send click button event
     *
     * @param array $user
     * @param array $page
     * @param array $properties
     * @param string $eventId
     * @param int $eventTime
     *
     * @return string
     * @throws JsonException
     */
    public function sendClickButtonEvent(
        array $user,
        array $page,
        array $properties,
        string $eventId,
        int $eventTime
    ): string {
        $eventData = [
            'event' => EventTypes::CLICK_BUTTON,
            'event_time' => $eventTime,
            'event_id' => $eventId,
            'user' => $user,
            'page' => $page,
            'properties' => $properties,];

        return $this->sendEvent(EventTypes::CLICK_BUTTON, $eventData);
    }

    /**
     * Send download event
     *
     * @param array $user
     * @param array $page
     * @param array $properties
     * @param string $eventId
     * @param int $eventTime
     *
     * @return string
     * @throws JsonException
     */
    public function sendDownloadEvent(
        array $user,
        array $page,
        array $properties,
        string $eventId,
        int $eventTime
    ): string {
        $eventData = [
            'event' => EventTypes::DOWNLOAD,
            'event_time' => $eventTime,
            'event_id' => $eventId,
            'user' => $user,
            'page' => $page,
            'properties' => $properties,];

        return $this->sendEvent(EventTypes::DOWNLOAD, $eventData);
    }

    /**
     * Send submit form event
     *
     * @param array $user
     * @param array $page
     * @param array $properties
     * @param string $eventId
     * @param int $eventTime
     *
     * @return string
     * @throws JsonException
     */
    public function sendSubmitFormEvent(
        array $user,
        array $page,
        array $properties,
        string $eventId,
        int $eventTime
    ): string {
        $eventData = [
            'event' => EventTypes::SUBMIT_FORM,
            'event_time' => $eventTime,
            'event_id' => $eventId,
            'user' => $user,
            'page' => $page,
            'properties' => $properties,];

        return $this->sendEvent(EventTypes::SUBMIT_FORM, $eventData);
    }

    /**
     * Send subscribe event
     *
     * @param array $user
     * @param array $page
     * @param array $properties
     * @param string $eventId
     * @param int $eventTime
     *
     * @return string
     * @throws JsonException
     */
    public function sendSubscribeEvent(
        array $user,
        array $page,
        array $properties,
        string $eventId,
        int $eventTime
    ): string {
        $eventData = [
            'event' => EventTypes::SUBSCRIBE,
            'event_time' => $eventTime,
            'event_id' => $eventId,
            'user' => $user,
            'page' => $page,
            'properties' => $properties,];

        return $this->sendEvent(EventTypes::SUBSCRIBE, $eventData);
    }

    /**
     * Send contact event
     *
     * @param array $user
     * @param array $page
     * @param array $properties
     * @param string $eventId
     * @param int $eventTime
     *
     * @return string
     * @throws JsonException
     */
    public function sendContactEvent(
        array $user,
        array $page,
        array $properties,
        string $eventId,
        int $eventTime
    ): string {
        $eventData = [
            'event' => EventTypes::CONTACT,
            'event_time' => $eventTime,
            'event_id' => $eventId,
            'user' => $user,
            'page' => $page,
            'properties' => $properties,];

        return $this->sendEvent(EventTypes::CONTACT, $eventData);
    }

    /**
     * Send search event
     *
     * @param array $user
     * @param array $page
     * @param array $properties
     * @param string $eventId
     * @param int $eventTime
     *
     * @return string
     * @throws JsonException
     */
    public function sendSearchEvent(
        array $user,
        array $page,
        array $properties,
        string $eventId,
        int $eventTime
    ): string {
        $eventData = [
            'event' => EventTypes::SEARCH,
            'event_time' => $eventTime,
            'event_id' => $eventId,
            'user' => $user,
            'page' => $page,
            'properties' => $properties,];

        return $this->sendEvent(EventTypes::SEARCH, $eventData);
    }

    /**
     * Send event
     *
     * @param string $eventType
     * @param array $eventData
     *
     * @return string
     * @throws JsonException
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    private function sendEvent(string $eventType, array $eventData): string
    {
        $headers = [
            'Content-Type' => 'application/json',
            'Access-Token' => $this->config->getValue('tiktok/Api/access_token')];

        $body = [
            'event_source' => 'web',
            'event_source_id' => $this->config->getValue('tiktok/Api/pixel_code'),
            'partner_name' => $this->config->getValue('tiktok/Api/partner_name'),
            'data' => [$eventData]];

        $this->httpClient->setHeaders($headers);
        $this->httpClient->post($this->url, json_encode($body, JSON_THROW_ON_ERROR));

        return $this->httpClient->getBody();
    }
}

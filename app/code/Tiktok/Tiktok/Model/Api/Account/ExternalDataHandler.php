<?php
declare(strict_types=1);

namespace Tiktok\Tiktok\Model\Api\Account;

use Exception;
use Tiktok\Tiktok\Logger\TiktokLogger;
use Tiktok\Tiktok\Model\Api\TiktokApiClientBuilder;
use Tiktok\Tiktok\Model\Config\ScopeManager;
use Tiktok\Tiktok\Model\Config\StoreAttributeValidation;
use DateTime;
use GuzzleHttp\Client;
use JsonException;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use Laminas\Uri\Uri as UriHandler;

/**
 * TikTok API External Data Handler
 *
 * @internal This class should only be created via a TiktokApiClientBuilder.
 * @see TiktokApiClientBuilder
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.CyclomaticComplexity)
 */
class ExternalDataHandler
{
    /**
     * API Version
     */
    private const API_VERSION = '1.0';

    /**
     * Auto approve app key
     */
    private const OPEN_SOURCE_TOKEN = '244e1de7-8dad-4656-a859-8dc09eea299d';

    /**
     * @var \GuzzleHttp\Client
     */
    private Client $httpClient;

    /**
     * @var \Tiktok\Tiktok\Logger\TiktokLogger
     */
    private TiktokLogger $logger;

    /**
     * @var \Tiktok\Tiktok\Model\Config\StoreAttributeValidation
     */
    private StoreAttributeValidation $storeAttributeValidation;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    private StoreManagerInterface $storeManager;

    /**
     * @var \Magento\Framework\UrlInterface
     */
    private UrlInterface $urlBuilder;

    /**
     * @var \Tiktok\Tiktok\Model\Config\ScopeManager
     */
    private ScopeManager $scopeManager;

    /**
     * Init dependencies
     *
     * @param \Tiktok\Tiktok\Model\Config\ScopeManager $scopeManager
     * @param \GuzzleHttp\Client $httpClient
     * @param \Tiktok\Tiktok\Logger\TiktokLogger $logger
     * @param \Tiktok\Tiktok\Model\Config\StoreAttributeValidation $storeAttributeValidation
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Framework\UrlInterface $urlBuilder
     * @param \Laminas\Uri\Uri $urlHandler
     */
    public function __construct(
        ScopeManager $scopeManager,
        Client $httpClient,
        TiktokLogger $logger,
        StoreAttributeValidation $storeAttributeValidation,
        StoreManagerInterface $storeManager,
        UrlInterface $urlBuilder,
        private readonly UriHandler $urlHandler
    ) {
        $this->scopeManager = $scopeManager;
        $this->httpClient = $httpClient;
        $this->logger = $logger;
        $this->storeAttributeValidation = $storeAttributeValidation;
        $this->storeManager = $storeManager;
        $this->urlBuilder = $urlBuilder;
    }

    /**
     * Return external data
     *
     * @return false|string
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws JsonException
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getExternalData(): false|string
    {
        $redirectUri = $this->scopeManager->getRedirectUri();
        $externalBusinessId = $this->scopeManager->getExternalBusinessId();
        $smbId = $this->scopeManager->getSmbId();
        $appId = $this->scopeManager->getAppId();
        $externalDataKey = $this->scopeManager->getExternalDataKey();

        if (!$redirectUri || !$externalBusinessId || !$smbId || !$appId || !$externalDataKey) {
            $smbName = $this->storeManager->getStore()->getName();
            if (!$redirectUri) {
                $redirectUri = $this->urlBuilder->getUrl('tiktok/account/onboardingcallback');
                $this->scopeManager->setRedirectUri($redirectUri);
            }
            if (!$externalBusinessId) {
                $externalBusinessId = uniqid(
                    'tt4b_magento_' . preg_replace('/\s+/', '', $smbName)
                );
                $this->scopeManager->setExternalBusinessId($externalBusinessId);
            }
            if (!$smbId) {
                $smbId = $externalBusinessId . preg_replace('/[^A-Za-z0-9\-]/', '', $redirectUri);
                $this->scopeManager->setSmbId($smbId);
            }

            $appResponse = $this->createOpenSourceApp(
                $smbId,
                $this->scopeManager->getBusinessPlatformId(),
                $redirectUri
            );

            if (!$appResponse
                || !array_key_exists('data', $appResponse)
                || !array_key_exists('app_id', $appResponse['data'])) {
                $this->logger->info('Failed to create TikTok Open Source App.');
                return false;
            }

            $appId = $appResponse['data']['app_id'];
            $externalDataKey = $appResponse['data']['external_data_key'];
            $appSecret = $appResponse['data']['app_secret'];

            $this->scopeManager->setRedirectUri($redirectUri);
            $this->scopeManager->setExternalBusinessId($externalBusinessId);
            $this->scopeManager->setSmbId($smbId);
            $this->scopeManager->setAppId($appId);
            $this->scopeManager->setExternalDataKey($externalDataKey);
            $this->scopeManager->setAppSecret($appSecret);
        }

        $timestamp = (new DateTime())->getTimestamp() * 1000;
        $data = [
            'business_platform' => $this->scopeManager->getBusinessPlatformId(),
            'external_business_id' => $externalBusinessId,
            'version' => self::API_VERSION,
            'timestamp' => $timestamp,
            'locale' => $this->storeAttributeValidation->getStoreLocale(),
            'email' => 'tiktok@example.com',
            'app_id' => $appId,
            'timezone' => $this->scopeManager->getGmtTimezone(),
            'currency' => $this->storeAttributeValidation->getCurrency(),
            'store_name' => $this->storeManager->getStore()->getName(),
            'website_url' => $this->storeManager->getStore()->getBaseUrl(),
            'domain' => $this->urlHandler->parse($this->storeManager->getStore()->getBaseUrl())->getHost(),
            'redirect_uri' => $redirectUri,
            'state' => $this->scopeManager->getWebsiteId()
        ];

        if ($this->storeManager->getStore()->getPhone()) {
            $data['phone_number'] = $this->storeManager->getStore()->getPhone();
        }
        if ($this->storeManager->getStore()->getEmailAddress()) {
            $data['email'] = $this->storeManager->getStore()->getEmailAddress();
        }

        $data['country_region'] = $this->scopeManager->getCountryId() ?? 'US';

        $hmacData = $this->concatHmacData($data);
        $hmac = $this->generateHmac($hmacData, $externalDataKey);
        $data['hmac'] = $hmac;

        return base64_encode(
            json_encode(
                $data,
                JSON_THROW_ON_ERROR
                | JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
            )
        );
    }

    /**
     * Create open source app
     *
     * @param mixed $smbId
     * @param mixed $smbName
     * @param mixed $redirectUri
     *
     * @return false|mixed
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws JsonException
     */

    public function createOpenSourceApp(mixed $smbId, mixed $smbName, mixed $redirectUri): mixed
    {
        $url = 'https://ads.tiktok.com/marketing_api/api/developer/app/create_auto_approve/';

        $params = [
            'business_platform' => $this->scopeManager->getBusinessPlatformId(),
            'smb_id' => $smbId,
            'smb_name' => $smbName,
            'redirect_url' => $redirectUri
        ];

        foreach ($params as $key => $value) {
            if ($value === null) {
                $this->logger->error('Missing required parameter: ' . $key);
                return false;
            }
        }

        $this->logger->info('Request to TikTok: ' . json_encode($params, JSON_THROW_ON_ERROR));
        try {
            $response = $this->httpClient->post($url, [
                'json' => $params,
                'headers' => [
                    'Access-Token' => self::OPEN_SOURCE_TOKEN,
                    'Content-Type' => 'application/json',
                    'Referer' => 'https://ads.tiktok.com']]);

            $responseBody = $response->getBody()->getContents();
            $statusCode = $response->getStatusCode();

            $this->logger->info('Response from TikTok: ' . $responseBody);

            if ($statusCode === 200) {
                return json_decode($responseBody, true, 512, JSON_THROW_ON_ERROR);
            }
        } catch (Exception $e) {
            $this->logger->info('Error during TikTok API call: ' . $e->getMessage());
        }
        return false;
    }

    /**
     * Concat HMAC Data
     *
     * @param array $data
     *
     * @return string
     */
    private function concatHmacData(array $data): string
    {
        return "version={$data['version']}"
            . "&timestamp={$data['timestamp']}"
            . "&locale={$data['locale']}"
            . "&business_platform={$data['business_platform']}"
            . "&external_business_id={$data['external_business_id']}";
    }

    /**
     * Generate HMAC
     *
     * @param string $data
     * @param string $secret
     *
     * @return string
     */
    private function generateHmac(string $data, string $secret): string
    {
        return hash_hmac('sha256', $data, $secret);
    }

    /**
     * Return logger
     *
     * @return TiktokLogger
     */
    public function getLogger(): TiktokLogger
    {
        return $this->logger;
    }

    /**
     * Return HTTP Client
     *
     * @return Client
     */
    public function getHttpClient(): Client
    {
        return $this->httpClient;
    }
}

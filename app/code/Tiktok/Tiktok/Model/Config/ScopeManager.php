<?php

declare(strict_types=1);

namespace Tiktok\Tiktok\Model\Config;

use DateInvalidTimeZoneException;
use DateMalformedStringException;
use DateTime;
use DateTimeZone;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * TikTok API Scope manager class
 * @SuppressWarnings(PHPMD.ExcessivePublicCount)
 */
class ScopeManager
{
    /**
     * Module config paths
     */
    public const XPATH_TIKTOK_REDIRECT_URI = 'tiktok/general/redirect_uri';
    public const XPATH_TIKTOK_EXTERNAL_BUSINESS_ID = 'tiktok/general/external_business_id';
    public const XPATH_TIKTOK_SMB_ID = 'tiktok/general/smb_id';
    public const XPATH_TIKTOK_APP_ID = 'tiktok/general/app_id';
    public const XPATH_TIKTOK_EXTERNAL_DATA_KEY = 'tiktok/general/external_data_key';
    public const XPATH_TIKTOK_AUTH_CODE = 'tiktok/auth/auth_code';
    public const XPATH_TIKTOK_CODE = 'tiktok/auth/code';
    public const XPATH_TIKTOK_BUSINESS_PLATFORM = 'tiktok/api/business_platform';
    public const XPATH_TIKTOK_APP_SECRET = 'tiktok/api/app_secret';
    public const XPATH_TIKTOK_ACCESS_TOKEN = 'tiktok/api/access_token';
    public const XPATH_TIKTOK_BC_ID = 'tiktok/api/bc_id';
    public const XPATH_TIKTOK_CATALOG_ID = 'tiktok/api/catalog_id';
    public const XPATH_TIKTOK_FEED_ID = 'tiktok/api/feed_id';
    public const XPATH_TIKTOK_CORE_USER_ID = 'tiktok/api/core_user_id';
    public const XPATH_TIKTOK_PIXEL_CODE = 'tiktok/api/pixel_code';

    public const XPATH_TIKTOK_PIXEL_TRACKING_ENABLE = 'tiktok/pixel_tracking/enable';
    public const XPATH_TIKTOK_PIXEL_TRACKING_ADVANCED_USER_TRACKING = 'tiktok/pixel_tracking/advanced_user_tracking';
    public const XPATH_TIKTOK_PIXEL_TRACKING_ENABLE_TP_COOKIE = 'tiktok/pixel_tracking/enable_tp_cookie';
    public const XPATH_TIKTOK_MANAGE_URL = 'tiktok/api/manage_url';
    public const XPATH_TIKTOK_AUTH_URL = 'tiktok/api/auth_url';
    public const XPATH_TIKTOK_SYNC_FREQUENCY = 'tiktok/sync/sync_frequency';
    public const XPATH_TIKTOK_SYNC_CUSTOM_ATTRIBUTE_MAP_1 = 'tiktok/sync/custom_labels/custom_label_1';
    public const XPATH_TIKTOK_SYNC_CUSTOM_ATTRIBUTE_MAP_2 = 'tiktok/sync/custom_labels/custom_label_2';
    public const XPATH_TIKTOK_SYNC_CUSTOM_ATTRIBUTE_MAP_3 = 'tiktok/sync/custom_labels/custom_label_3';
    public const XPATH_TIKTOK_SYNC_CUSTOM_ATTRIBUTE_MAP_4 = 'tiktok/sync/custom_labels/custom_label_4';
    public const XPATH_TIKTOK_SYNC_CUSTOM_ATTRIBUTE_MAP_5 = 'tiktok/sync/custom_labels/custom_label_5';
    public const XPATH_TIKTOK_LOG_LEVEL = 'tiktok/logging/log_level';

    public const XPATH_TIKTOK_ENABLE = 'tiktok/general/enable';

    public const XPATH_TIKTOK_COUNTRY_ID = 'general/store_information/country_id';

    /**
     * Encrypted Paths
     */
    private const ENCRYPTED_PATHS = [
        self::XPATH_TIKTOK_AUTH_CODE,
        self::XPATH_TIKTOK_APP_SECRET,
        self::XPATH_TIKTOK_ACCESS_TOKEN
    ];

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    private ScopeConfigInterface $scopeConfig;

    /**
     * @var \Magento\Framework\App\Config\Storage\WriterInterface
     */
    private WriterInterface $configWriter;

    /**
     * @var \Magento\Framework\Encryption\EncryptorInterface
     */
    private EncryptorInterface $encryptor;

    /**
     * @var int|null
     */
    private ?int $websiteId = null;

    /**
     * Init dependencies
     *
     * @param int $websiteId
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Framework\App\Config\Storage\WriterInterface $configWriter
     * @param \Magento\Framework\Encryption\EncryptorInterface $encryptor
     */
    public function __construct(
        int $websiteId,
        ScopeConfigInterface $scopeConfig,
        WriterInterface $configWriter,
        EncryptorInterface $encryptor
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->configWriter = $configWriter;
        $this->encryptor = $encryptor;
        $this->setWebsiteId($websiteId);
    }

    /**
     * Perform check if pixel tracking is enabled
     *
     * @return string|null
     */
    public function isPixelTrackingEnabled(): ?string
    {
        return $this->getValue(self::XPATH_TIKTOK_PIXEL_TRACKING_ENABLE);
    }

    /**
     * Retrieve config value by path
     *
     * @param string $path
     *
     * @return mixed|string
     */
    private function getValue(string $path)
    {
        $value = $this->scopeConfig->getValue($path, ScopeInterface::SCOPE_WEBSITES, $this->getWebsiteId());

        if ($value && in_array($path, self::ENCRYPTED_PATHS, true)) {
            return $this->encryptor->decrypt($value);
        }

        return $value;
    }

    /**
     * Return website ID
     *
     * @return int
     */
    public function getWebsiteId(): int
    {
        return $this->websiteId;
    }

    /**
     * Set website ID
     *
     * @param int $websiteId
     *
     * @return $this
     */
    public function setWebsiteId(int $websiteId): self
    {
        $this->websiteId = $websiteId;
        return $this;
    }

    /**
     * Indicates whether advanced tracking features for users are turned on
     *
     * @return string|null
     */
    public function isAdvancedUserTrackingEnabled(): ?string
    {
        return $this->getValue(self::XPATH_TIKTOK_PIXEL_TRACKING_ADVANCED_USER_TRACKING);
    }

    /**
     * Indicates whether pixel tracking Third-Party Cookie is enabled
     *
     * @return string|null
     */
    public function isTpCookieEnabled(): ?string
    {
        return $this->getValue(self::XPATH_TIKTOK_PIXEL_TRACKING_ENABLE_TP_COOKIE);
    }

    /**
     * Checks if module is currently active based on access token, app ID, and app secret
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return !empty($this->getAccessToken()) && !empty($this->getAppId()) && !empty($this->getAppSecret());
    }

    /**
     * Returns the access token
     *
     * @return string|null
     */
    public function getAccessToken(): ?string
    {
        return $this->getValue(self::XPATH_TIKTOK_ACCESS_TOKEN);
    }

    /**
     * Returns the app ID
     *
     * @return string|null
     */
    public function getAppId(): ?string
    {
        return $this->getValue(self::XPATH_TIKTOK_APP_ID);
    }

    // General Configuration

    /**
     * Returns the app secret
     *
     * @return string|null
     */
    public function getAppSecret(): ?string
    {
        return $this->getValue(self::XPATH_TIKTOK_APP_SECRET);
    }

    /**
     * Returns the redirect URI
     *
     * @return string|null
     */
    public function getRedirectUri(): ?string
    {
        return $this->getValue(self::XPATH_TIKTOK_REDIRECT_URI);
    }

    /**
     * Returns the country ID
     *
     * @return string|null
     */
    public function getCountryId(): ?string
    {
        return $this->getValue(self::XPATH_TIKTOK_COUNTRY_ID);
    }

    /**
     * Set the redirect URI
     *
     * @param string $uri
     *
     * @return void
     */
    public function setRedirectUri(string $uri): void
    {
        $this->setValue(self::XPATH_TIKTOK_REDIRECT_URI, $uri);
        $this->scopeConfig->clean();
    }

    /**
     * Set config value for specified path
     *
     * @param string $path
     * @param mixed $value
     *
     * @return void
     */
    private function setValue(string $path, mixed $value): void
    {
        if ($value && in_array($path, self::ENCRYPTED_PATHS, true)) {
            $value = $this->encryptor->encrypt($value);
        }

        $this->configWriter->save($path, $value, ScopeInterface::SCOPE_WEBSITES, $this->getWebsiteId());
    }

    /**
     * Returns external business ID
     *
     * @return string|null
     */
    public function getExternalBusinessId(): ?string
    {
        return $this->getValue(self::XPATH_TIKTOK_EXTERNAL_BUSINESS_ID);
    }

    /**
     * Set external business ID
     *
     * @param string $id
     *
     * @return void
     */
    public function setExternalBusinessId(string $id): void
    {
        $this->setValue(self::XPATH_TIKTOK_EXTERNAL_BUSINESS_ID, $id);
        $this->scopeConfig->clean();
    }

    /**
     * Returns business identifier
     *
     * @return string|null
     */
    public function getSmbId(): ?string
    {
        return $this->getValue(self::XPATH_TIKTOK_SMB_ID);
    }

    /**
     * Set business identifier
     *
     * @param string $id
     *
     * @return void
     */
    public function setSmbId(string $id): void
    {
        $this->setValue(self::XPATH_TIKTOK_SMB_ID, $id);
        $this->scopeConfig->clean();
    }

    /**
     * Set App ID
     *
     * @param string $id
     *
     * @return void
     */
    public function setAppId(string $id): void
    {
        $this->setValue(self::XPATH_TIKTOK_APP_ID, $id);
        $this->scopeConfig->clean();
    }

    // Auth Configuration

    /**
     * Returns external data key
     *
     * @return string|null
     */
    public function getExternalDataKey(): ?string
    {
        return $this->getValue(self::XPATH_TIKTOK_EXTERNAL_DATA_KEY);
    }

    /**
     * Set external data key
     *
     * @param string $key
     *
     * @return void
     */
    public function setExternalDataKey(string $key): void
    {
        $this->setValue(self::XPATH_TIKTOK_EXTERNAL_DATA_KEY, $key);
        $this->scopeConfig->clean();
    }

    /**
     * Returns auth code
     *
     * @return string|null
     */
    public function getAuthCode(): ?string
    {
        return $this->getValue(self::XPATH_TIKTOK_AUTH_CODE);
    }

    /**
     * Set auth code
     *
     * @param string $code
     *
     * @return void
     */
    public function setAuthCode(string $code): void
    {
        $this->setValue(self::XPATH_TIKTOK_AUTH_CODE, $code);
        $this->scopeConfig->clean();
    }

    // API Configuration

    /**
     * Returns code
     *
     * @return string|null
     */
    public function getCode(): ?string
    {
        return $this->getValue(self::XPATH_TIKTOK_CODE);
    }

    /**
     * Set code
     *
     * @param string $code
     *
     * @return void
     */
    public function setCode(string $code): void
    {
        $this->setValue(self::XPATH_TIKTOK_CODE, $code);
    }

    /**
     * Returns Business platform ID
     *
     * @return string|null
     */
    public function getBusinessPlatformId(): ?string
    {
        return $this->getValue(self::XPATH_TIKTOK_BUSINESS_PLATFORM);
    }

    /**
     * Set Business platform ID
     *
     * @param string $platform
     *
     * @return void
     */
    public function setBusinessPlatformId(string $platform): void
    {
        $this->setValue(self::XPATH_TIKTOK_BUSINESS_PLATFORM, $platform);
        $this->scopeConfig->clean();
    }

    /**
     * Set app secret
     *
     * @param string $secret
     *
     * @return void
     */
    public function setAppSecret(string $secret): void
    {
        $this->setValue(self::XPATH_TIKTOK_APP_SECRET, $secret);
        $this->scopeConfig->clean();
    }

    /**
     * Set access token
     *
     * @param string $token
     *
     * @return void
     */
    public function setAccessToken(string $token): void
    {
        $this->setValue(self::XPATH_TIKTOK_ACCESS_TOKEN, $token);
        $this->scopeConfig->clean();
    }

    /**
     * Returns business center ID
     *
     * @return string|null
     */
    public function getBcId(): ?string
    {
        return $this->getValue(self::XPATH_TIKTOK_BC_ID);
    }

    /**
     * Set business center ID
     *
     * @param string $id
     *
     * @return void
     */
    public function setBcId(string $id): void
    {
        $this->setValue(self::XPATH_TIKTOK_BC_ID, $id);
        $this->scopeConfig->clean();
    }

    /**
     * Returns catalog ID
     *
     * @return string|null
     */
    public function getCatalogId(): ?string
    {
        return $this->getValue(self::XPATH_TIKTOK_CATALOG_ID);
    }

    // Business Profile Configuration

    /**
     * Returns feed ID
     *
     * @return string|null
     */
    public function getFeedId(): ?string
    {
        return $this->getValue(self::XPATH_TIKTOK_FEED_ID);
    }

    /**
     * Returns manage URL
     *
     * @return string|null
     */
    public function getManageUrl(): ?string
    {
        return $this->getValue(self::XPATH_TIKTOK_MANAGE_URL);
    }

    /**
     * Returns auth URL
     *
     * @return string|null
     */
    public function getAuthUrl(): ?string
    {
        return $this->getValue(self::XPATH_TIKTOK_AUTH_URL);
    }

    /**
     * Set catalog ID
     *
     * @param string $id
     *
     * @return void
     */
    public function setCatalogId(string $id): void
    {
        $this->setValue(self::XPATH_TIKTOK_CATALOG_ID, $id);
    }

    /**
     * Set feed ID
     *
     * @param string $id
     *
     * @return void
     */
    public function setFeedId(string $id): void
    {
        $this->setValue(self::XPATH_TIKTOK_FEED_ID, $id);
        $this->scopeConfig->clean();
    }

    /**
     * Returns core user ID
     *
     * @return string|null
     */
    public function getCoreUserId(): ?string
    {
        return $this->getValue(self::XPATH_TIKTOK_CORE_USER_ID);
    }

    /**
     * Set core user ID
     *
     * @param string $id
     *
     * @return void
     */
    public function setCoreUserId(string $id): void
    {
        $this->setValue(self::XPATH_TIKTOK_CORE_USER_ID, $id);
        $this->scopeConfig->clean();
    }

    /**
     * Returns pixel code
     *
     * @return string|null
     */
    public function getPixelCode(): ?string
    {
        return $this->getValue(self::XPATH_TIKTOK_PIXEL_CODE);
    }

    /**
     * Set pixel code
     *
     * @param string $code
     *
     * @return void
     */
    public function setPixelCode(string $code): void
    {
        $this->setValue(self::XPATH_TIKTOK_PIXEL_CODE, $code);
        $this->scopeConfig->clean();
    }

    /**
     * Retrieve cron frequency
     *
     * @return string|null
     */
    public function getSyncFrequency(): ?string
    {
        return $this->getValue(self::XPATH_TIKTOK_SYNC_FREQUENCY);
    }

    /**
     * Return custom labels
     *
     * @return array
     */
    public function getCustomLabels(): array
    {
        return [
            'custom_label_0' => $this->getValue(self::XPATH_TIKTOK_SYNC_CUSTOM_ATTRIBUTE_MAP_1) ?? '',
            'custom_label_1' => $this->getValue(self::XPATH_TIKTOK_SYNC_CUSTOM_ATTRIBUTE_MAP_2) ?? '',
            'custom_label_2' => $this->getValue(self::XPATH_TIKTOK_SYNC_CUSTOM_ATTRIBUTE_MAP_3) ?? '',
            'custom_label_3' => $this->getValue(self::XPATH_TIKTOK_SYNC_CUSTOM_ATTRIBUTE_MAP_4) ?? '',
            'custom_label_4' => $this->getValue(self::XPATH_TIKTOK_SYNC_CUSTOM_ATTRIBUTE_MAP_5) ?? ''];
    }

    // Logging Configuration

    /**
     * Return log level
     *
     * @return string|null
     */
    public function getLogLevel(): ?string
    {
        return $this->getValue(self::XPATH_TIKTOK_LOG_LEVEL);
    }

    /**
     * Set log level
     *
     * @param string $level
     * @return void
     */
    public function setLogLevel(string $level): void
    {
        $this->setValue(self::XPATH_TIKTOK_LOG_LEVEL, $level);
    }

    /**
     * Checks if module is configured based on access token, app ID, and app secret
     *
     * @return bool
     */
    public function isConfigured(): bool
    {
        return !empty($this->getAccessToken()) && !empty($this->getAppId()) && !empty($this->getAppSecret());
    }

    /**
     * Clear TikTok Auth Data
     *
     * @return void
     */
    public function clearAuthData(): void
    {
        $this->setValue(self::XPATH_TIKTOK_AUTH_CODE, null);
        $this->setValue(self::XPATH_TIKTOK_ACCESS_TOKEN, null);
        $this->setValue(self::XPATH_TIKTOK_CATALOG_ID, null);
        $this->setValue(self::XPATH_TIKTOK_FEED_ID, null);
        $this->setValue(self::XPATH_TIKTOK_CORE_USER_ID, null);
        $this->setValue(self::XPATH_TIKTOK_PIXEL_CODE, null);
        $this->scopeConfig->clean();
    }

    /**
     * Returns GMT Timezone
     *
     * @return string
     * @throws DateInvalidTimeZoneException
     * @throws DateMalformedStringException
     */
    public function getGmtTimezone(): string
    {
        $timezone = $this->getValue('general/locale/timezone');

        if (empty($timezone)) {
            return 'GMT+00:00';
        }

        $dateTime = new DateTime('now', new DateTimeZone($timezone));
        $gmtTimezone = new DateTimeZone('GMT');
        $dateTime->setTimezone($gmtTimezone);

        $offsetInSeconds = $gmtTimezone->getOffset($dateTime);
        $offsetHours = (int)($offsetInSeconds / 3600);
        $offsetMinutes = abs(($offsetInSeconds % 3600) / 60);

        return sprintf('GMT%+03d:%02d', $offsetHours, $offsetMinutes);
    }
}

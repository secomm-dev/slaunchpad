<?php
declare(strict_types=1);

namespace Tiktok\Tiktok\Model\Api;

use Tiktok\Tiktok\Model\Api\Account\BusinessCenterRequestFactory;
use Tiktok\Tiktok\Model\Api\Account\DisconnectServiceFactory;
use Tiktok\Tiktok\Model\Api\Account\ExternalDataHandlerFactory;
use Tiktok\Tiktok\Model\Api\Account\ProfileLinkedToAppRequestFactory;
use Tiktok\Tiktok\Model\Api\Account\TrustSignalsFactory;
use Tiktok\Tiktok\Model\Api\Auth\TokenHandlerFactory;
use Tiktok\Tiktok\Model\Api\Catalog\CatalogServiceFactory;
use Tiktok\Tiktok\Model\Api\Catalog\JsonUploadServiceFactory;
use Tiktok\Tiktok\Model\Config\ScopeManager;
use Tiktok\Tiktok\Model\Api\Event\EventSenderFactory;
use GuzzleHttp\Exception\GuzzleException;
use JsonException;
use Magento\Framework\Exception\LocalizedException;

/**
 * API client class for TikTok.
 *
 * @internal This should only be created via a TiktokApiClientBuilder instance.
 * @see TiktokApiClientBuilder
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class TiktokApiClient
{
    /**
     * @var \Tiktok\Tiktok\Model\Api\Account\BusinessCenterRequestFactory
     */
    private BusinessCenterRequestFactory $businessCenterRequestFactory;

    /**
     * @var \Tiktok\Tiktok\Model\Api\Account\DisconnectServiceFactory
     */
    private DisconnectServiceFactory $disconnectServiceFactory;

    /**
     * @var \Tiktok\Tiktok\Model\Api\Account\ProfileLinkedToAppRequestFactory
     */
    private ProfileLinkedToAppRequestFactory $profileLinkedToAppRequestFactory;

    /**
     * @var \Tiktok\Tiktok\Model\Api\Catalog\CatalogServiceFactory
     */
    private CatalogServiceFactory $catalogServiceFactory;

    /**
     * @var \Tiktok\Tiktok\Model\Api\Catalog\JsonUploadServiceFactory
     */
    private JsonUploadServiceFactory $jsonUploadServiceFactory;

    /**
     * @var \Tiktok\Tiktok\Model\Config\ScopeManager
     */
    private ScopeManager $scopeManager;

    /**
     * @var \Tiktok\Tiktok\Model\Api\Account\ExternalDataHandlerFactory
     */
    private ExternalDataHandlerFactory $externalDataHandlerFactory;

    /**
     * @var \Tiktok\Tiktok\Model\Api\Auth\TokenHandlerFactory
     */
    private TokenHandlerFactory $tokenHandlerFactory;

    /**
     * @var \Tiktok\Tiktok\Model\Api\Event\EventSenderFactory
     */
    private EventSenderFactory $eventSenderFactory;

    /**
     * @var \Tiktok\Tiktok\Model\Api\Account\TrustSignalsFactory
     */
    private TrustSignalsFactory $trustSignalsFactory;

    /**
     * Init dependencies
     *
     * @param \Tiktok\Tiktok\Model\Config\ScopeManager $scopeManager
     * @param \Tiktok\Tiktok\Model\Api\Account\BusinessCenterRequestFactory $businessCenterRequestFactory
     * @param \Tiktok\Tiktok\Model\Api\Account\DisconnectServiceFactory $disconnectServiceFactory
     * @param \Tiktok\Tiktok\Model\Api\Account\ProfileLinkedToAppRequestFactory $profileLinkedToAppRequestFactory
     * @param \Tiktok\Tiktok\Model\Api\Catalog\CatalogServiceFactory $catalogServiceFactory
     * @param \Tiktok\Tiktok\Model\Api\Catalog\JsonUploadServiceFactory $jsonUploadServiceFactory
     * @param \Tiktok\Tiktok\Model\Api\Account\ExternalDataHandlerFactory $externalDataHandlerFactory
     * @param \Tiktok\Tiktok\Model\Api\Auth\TokenHandlerFactory $tokenHandlerFactory
     * @param \Tiktok\Tiktok\Model\Api\Event\EventSenderFactory $eventSenderFactory
     * @param \Tiktok\Tiktok\Model\Api\Account\TrustSignalsFactory $trustSignalsFactory
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        ScopeManager $scopeManager,
        BusinessCenterRequestFactory $businessCenterRequestFactory,
        DisconnectServiceFactory $disconnectServiceFactory,
        ProfileLinkedToAppRequestFactory $profileLinkedToAppRequestFactory,
        CatalogServiceFactory $catalogServiceFactory,
        JsonUploadServiceFactory $jsonUploadServiceFactory,
        ExternalDataHandlerFactory $externalDataHandlerFactory,
        TokenHandlerFactory $tokenHandlerFactory,
        EventSenderFactory $eventSenderFactory,
        TrustSignalsFactory $trustSignalsFactory
    ) {
        $this->scopeManager = $scopeManager;
        $this->businessCenterRequestFactory = $businessCenterRequestFactory;
        $this->disconnectServiceFactory = $disconnectServiceFactory;
        $this->profileLinkedToAppRequestFactory = $profileLinkedToAppRequestFactory;
        $this->catalogServiceFactory = $catalogServiceFactory;
        $this->jsonUploadServiceFactory = $jsonUploadServiceFactory;
        $this->externalDataHandlerFactory = $externalDataHandlerFactory;
        $this->tokenHandlerFactory = $tokenHandlerFactory;
        $this->eventSenderFactory = $eventSenderFactory;
        $this->trustSignalsFactory = $trustSignalsFactory;
    }

    /**
     * Retrieve external data
     *
     * @return string
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws JsonException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getExternalData(): string
    {
        return $this->externalDataHandlerFactory->create(['scopeManager' => $this->scopeManager])->getExternalData();
    }

    /**
     * Retrieve business centers
     *
     * @param string|null $bcId
     * @param int $page
     * @param int $pageSize
     *
     * @return array
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getBusinessCenters(?string $bcId = null, int $page = 1, int $pageSize = 10): array
    {
        return $this->businessCenterRequestFactory->create([
            'scopeManager' => $this->scopeManager,
            'externalData' => $this->getExternalDataHandler(),
            'tokenHandler' => $this->getTokenHandler()])->getBusinessCenters($bcId, $page, $pageSize);
    }

    /**
     * Retrieve external data handler
     *
     * @return \Tiktok\Tiktok\Model\Api\Account\ExternalDataHandler
     */
    public function getExternalDataHandler()
    {
        return $this->externalDataHandlerFactory->create(['scopeManager' => $this->scopeManager]);
    }

    /**
     * Retrieve token handler
     *
     * @return \Tiktok\Tiktok\Model\Api\Auth\TokenHandler
     */
    public function getTokenHandler()
    {
        return $this->tokenHandlerFactory->create(['scopeManager' => $this->scopeManager]);
    }

    /**
     * Disconnect
     *
     * @return array output format ['success' => true/false, 'message' => '']
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function disconnect(): array
    {
        $disconnect = $this->disconnectServiceFactory->create([
            'scopeManager' => $this->scopeManager,
            'externalData' => $this->getExternalDataHandler(),
            'tokenHandler' => $this->getTokenHandler()]);
        return $disconnect->disconnect();
    }

    /**
     * Retrieve Profile
     *
     * @return array
     */
    public function getProfile(): array
    {
        return $this->profileLinkedToAppRequestFactory->create([
            'scopeManager' => $this->scopeManager,
            'externalData' => $this->getExternalDataHandler(),
            'tokenHandler' => $this->getTokenHandler()])
            ->getProfile();
    }

    /**
     * Retrieve catalogs
     *
     * @param string $bc_id
     * @param string|null $catalogId
     * @param int|null $page
     * @param int|null $pageSize
     *
     * @return array
     * @throws GuzzleException
     * @throws LocalizedException
     */
    public function getCatalogs(string $bc_id, ?string $catalogId = null, ?int $page = 1, ?int $pageSize = 100): array
    {
        return $this->catalogServiceFactory->create([
            'scopeManager' => $this->scopeManager,
            'externalData' => $this->getExternalDataHandler(),
            'tokenHandler' => $this->getTokenHandler()])->getCatalogs($bc_id, $catalogId, $page, $pageSize);
    }

    /**
     * Create Feed
     *
     * @param string $bcId
     * @param string $catalogId
     * @param string $updateMode
     * @param string $feedName
     *
     * @return array
     * @throws GuzzleException
     * @throws LocalizedException
     */
    public function createFeed(string $bcId, string $catalogId, string $updateMode, string $feedName = 'Magento'): array
    {
        return $this->catalogServiceFactory->create([
            'scopeManager' => $this->scopeManager,
            'externalData' => $this->getExternalDataHandler(),
            'tokenHandler' => $this->getTokenHandler()])->createFeed($bcId, $catalogId, $updateMode, $feedName);
    }

    /**
     * Upload catalog products
     *
     * @param string $bcId
     * @param string $catalogId
     * @param string $fileUrl
     * @param mixed $feedId
     * @param string|null $updateMode
     *
     * @return array
     * @throws GuzzleException
     * @throws LocalizedException
     */
    public function uploadCatalogProducts(
        string $bcId,
        string $catalogId,
        string $fileUrl,
        mixed $feedId,
        ?string $updateMode = null
    ): array {
        return $this->catalogServiceFactory->create([
            'scopeManager' => $this->scopeManager,
            'externalData' => $this->getExternalDataHandler(),
            'tokenHandler' => $this->getTokenHandler()])
            ->uploadCsvExport($bcId, $catalogId, $fileUrl, $feedId, $updateMode);
    }

    /**
     * Upload products
     *
     * @param string $bcId
     * @param string $catalogId
     * @param array $products
     * @param string|null $feedId
     *
     * @return array
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function uploadProductsJson(string $bcId, string $catalogId, array $products, ?string $feedId = null): array
    {
        return $this->jsonUploadServiceFactory->create([
            'scopeManager' => $this->scopeManager,
            'externalData' => $this->getExternalDataHandler(),
            'tokenHandler' => $this->getTokenHandler()])->uploadProductsJson($bcId, $catalogId, $products, $feedId);
    }

    /**
     * Remove products
     *
     * @param string $bcId
     * @param string $catalogId
     * @param array $skus
     * @param string|null $feedId
     *
     * @return array
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function removeProducts(string $bcId, string $catalogId, array $skus, ?string $feedId = null): array
    {
        return $this->jsonUploadServiceFactory->create(
            [
                'scopeManager' => $this->scopeManager,
                'externalData' => $this->getExternalDataHandler(),
                'tokenHandler' => $this->getTokenHandler()
            ]
        )->removeProductsRequest($bcId, $catalogId, $skus, $feedId);
    }

    /**
     * Create access token
     *
     * @param mixed $authCode
     * @return mixed
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function createAccessToken(mixed $authCode): mixed
    {
        return $this->tokenHandlerFactory->create(['scopeManager' => $this->scopeManager])
            ->createAccessToken($authCode);
    }

    /**
     * Get scope manager
     *
     * @return \Tiktok\Tiktok\Model\Config\ScopeManager
     */
    public function getScopeManager(): ScopeManager
    {
        return $this->scopeManager;
    }

    /**
     * Track batch of events through TikTok API
     *
     * @param array $events
     *
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function sendEvents(array $events): array
    {
        return $this->eventSenderFactory->create([
            'scopeManager' => $this->scopeManager,
            'externalData' => $this->getExternalDataHandler(),
            'tokenHandler' => $this->getTokenHandler()])->sendEvents($events);
    }

    /**
     * Track partner data through TikTok API
     *
     * @param array $data
     *
     * @return bool
     */
    public function sendTrustSignals(array $data): bool
    {
        return $this->trustSignalsFactory->create([
            'scopeManager' => $this->scopeManager,
            'externalData' => $this->getExternalDataHandler(),
            'tokenHandler' => $this->getTokenHandler()])->sendTrustSignals($data);
    }
}

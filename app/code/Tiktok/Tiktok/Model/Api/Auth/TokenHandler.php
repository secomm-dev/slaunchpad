<?php
declare(strict_types=1);

namespace Tiktok\Tiktok\Model\Api\Auth;

use Magento\Framework\Exception\LocalizedException;
use Tiktok\Tiktok\Model\Config\ScopeManager;
use Tiktok\Tiktok\Logger\TiktokLogger;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Magento\Integration\Api\Exception\UserTokenException;

/**
 * OAuth Service class.
 * This class is responsible for getting the access token using the OAuth flow.
 *
 * @internal This class should only be created via a TiktokApiClientBuilder.
 * @see TiktokApiClientBuilde
 */
class TokenHandler
{
    /**
     * Access Token Endpoint
     */
    protected const ACCESS_TOKEN_ENDPOINT = 'https://business-api.tiktok.com/open_api/v1.3/oauth2/access_token/';

    /**
     * @var \GuzzleHttp\Client
     */
    protected Client $client;

    /**
     * @var \Tiktok\Tiktok\Logger\TiktokLogger
     */
    protected TiktokLogger $logger;

    /**
     * @var \Tiktok\Tiktok\Model\Config\ScopeManager
     */
    private ScopeManager $scopeManager;

    /**
     * Init dependencies
     *
     * @param \GuzzleHttp\Client $client
     * @param \Tiktok\Tiktok\Model\Config\ScopeManager $scopeManager
     * @param \Tiktok\Tiktok\Logger\TiktokLogger $logger
     */
    public function __construct(Client $client, ScopeManager $scopeManager, TiktokLogger $logger)
    {
        $this->client = $client;
        $this->scopeManager = $scopeManager;
        $this->logger = $logger;
    }

    /**
     * Get the access token and refresh it if expired
     *
     * @return string|null
     * @throws UserTokenException
     */
    public function getAccessToken(): ?string
    {
        $accessToken = $this->scopeManager->getAccessToken();

        if (!$accessToken) {
            throw new UserTokenException(
                'No access token available. The tiktok BC account is not connected or authorized.'
            );
        }

        return $accessToken;
    }

    /**
     * Get Access Token using OAuth flow
     *
     * @param mixed $authCode
     * @return bool
     * @throws GuzzleException
     */
    public function createAccessToken(mixed $authCode): bool
    {
        try {
            $appId = $this->scopeManager->getAppId();
            $secret = $this->scopeManager->getAppSecret();

            $requestData = [
                'app_id' => $appId,
                'auth_code' => $authCode,
                'secret' => $secret];

            $response = $this->client->post(self::ACCESS_TOKEN_ENDPOINT, [
                'headers' => ['Content-Type' => 'application/json'],
                'json' => $requestData]);

            $responseBody = json_decode(
                $response->getBody()->getContents(),
                true,
                512,
                JSON_THROW_ON_ERROR
            );

            if (isset($responseBody['data']['access_token'])) {
                $accessToken = $responseBody['data']['access_token'];

                // Save the tokens and their expiry
                $this->scopeManager->setAccessToken($accessToken);

                return true;
            }
            throw new LocalizedException(__('Access token not found in response.'));
            // phpcs:ignore Magento2.Exceptions.ThrowCatch.ThrowCatch
        } catch (\GuzzleHttp\Exception\GuzzleException $e) {
            // Catch Guzzle-specific exceptions for network or HTTP errors.
            $this->logger->error('Error getting access token. HTTP request failed: ' . $e->getMessage());
            return false;
        } catch (\JsonException $e) {
            // Catch JSON-specific exceptions for decoding errors.
            $this->logger->error('Error getting access token. JSON decode failed: ' . $e->getMessage());
            return false;
        } catch (\Exception $e) {
            // Fallback for any other unexpected exceptions.
            $this->logger->error('Error getting access token: ' . $e->getMessage());
            return false;
        }
    }
}

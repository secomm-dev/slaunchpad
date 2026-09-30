<?php
declare(strict_types=1);

namespace Tiktok\Tiktok\Model\Api\Account;

use Tiktok\Tiktok\Model\Api\AbstractApiRequest;
use Tiktok\Tiktok\Model\Api\TiktokApiClientBuilder;
use Exception;

/**
 * TikTok Disconnect Service class.
 *
 * @internal This class should only be created via a TiktokApiClientBuilder.
 * @see TiktokApiClientBuilder
 */
class DisconnectService extends AbstractApiRequest
{
    /**
     * Endpoint Disconnect
     */
    private const ENDPOINT_DISCONNECT = 'tbp/v2.0/business_profile/disconnect';

    /**
     * Disconnect from TikTok using the creator token
     *
     * @return array
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function disconnect(): array
    {
        try {
            $externalBusinessId = $this->scopeManager->getExternalBusinessId();
            $businessPlatformId = $this->scopeManager->getBusinessPlatformId();
            $appId = $this->scopeManager->getAppId();
            $result = [
                'success' => true];

            $requestData = [
                'business_platform' => $businessPlatformId,
                'external_business_id' => $externalBusinessId,
                'app_id' => $appId,
                'is_setup_page' => 0];

            $response = $this->sendRequest(
                'POST',
                $requestData,
                self::ENDPOINT_DISCONNECT,
                false,
                [],
                null,
                true
            );

            // Ensure the response is valid
            if (!$response || $response['message'] !== 'OK') {
                $result['success'] = false;
                $result['message'] = __('Response from Tiktok: %1', $response['message'] ?? '');
            }

            return $result;
        } catch (Exception $e) {
            // Log the error
            $this->logger->error('Error disconnecting from TikTok: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()];
        }
    }
}

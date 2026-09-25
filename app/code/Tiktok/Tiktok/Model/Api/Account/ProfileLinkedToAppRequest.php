<?php
declare(strict_types=1);

namespace Tiktok\Tiktok\Model\Api\Account;

use Tiktok\Tiktok\Model\Api\AbstractApiRequest;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use JsonException;
use Magento\Integration\Api\Exception\UserTokenException;

/**
 * TikTok Profile Linked To App Request class
 *
 * @internal This class should only be created via a TiktokApiClientBuilder.
 * @see TiktokApiClientBuilder
 */
class ProfileLinkedToAppRequest extends AbstractApiRequest
{
    /**
     * Profile Link
     */
    private const PROFILE_LINK = 'open_api/v1.2/tbp/business_profile/get/';

    /**
     * Disconnect from TikTok using the creator token
     *
     * @return array|false
     */
    public function getProfile(): bool|array
    {
        return $this->getProfileFromConfig() ?: $this->retrieveProfile();
    }

    /**
     * Return profile from config
     *
     * @return false|array
     */
    private function getProfileFromConfig(): false|array
    {
        $bcId = $this->scopeManager->getBcId();
        $catalogId = $this->scopeManager->getCatalogId();
        $coreUserId = $this->scopeManager->getCoreUserId();
        $pixelCode = $this->scopeManager->getPixelCode();

        if ($bcId && $catalogId && $coreUserId && $pixelCode) {
            return [
                'bc_id' => $bcId,
                'catalog_id' => $catalogId,
                'core_user_id' => $coreUserId,
                'pixel_code' => $pixelCode];
        }

        return false;
    }

    /**
     * Retrieve Profile
     *
     * @return array
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws JsonException
     */
    private function retrieveProfile(): array
    {
        try {
            $requestData = [
                'business_platform' => $this->scopeManager->getBusinessPlatformId(),
                'external_business_id' => $this->scopeManager->getExternalBusinessId(),
                'full_data' => 1];

            $response = $this->sendRequest(
                'GET',
                $requestData,
                self::PROFILE_LINK,
                true,
                [],
                null,
                true
            );

            if (!$response || !isset($response['code']) || $response['message'] !== 'OK') {
                $message = $response['message'] ?? 'Failed to retrieve profile';
                $this->logger->error(__('Error during fetching profile: %1', $message));
                return [];
            } else {
                $this->scopeManager->setBcId($response['data']['bc_id']);
                $this->scopeManager->setCatalogId($response['data']['catalog_id']);
                $this->scopeManager->setCoreUserId($response['data']['core_user_id']);
                $this->scopeManager->setPixelCode($response['data']['pixel_code']);

                return [
                    'bc_id' => $response['data']['bc_id'],
                    'catalog_id' => $response['data']['catalog_id'],
                    'core_user_id' => $response['data']['core_user_id'],
                    'pixel_code' => $response['data']['pixel_code']];
            }
        } catch (UserTokenException $e) {
            $this->logger->error('Access TikTok Error: ' . $e->getMessage());
            return [];
        } catch (Exception $e) {
            // Log the error and return empty array
            $this->logger->error('Error disconnecting from TikTok: ' . $e->getMessage());
            return [];
        }
    }
}

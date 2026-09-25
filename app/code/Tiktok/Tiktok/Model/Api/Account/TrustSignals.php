<?php
declare(strict_types=1);

namespace Tiktok\Tiktok\Model\Api\Account;

use Magento\Integration\Api\Exception\UserTokenException;
use GuzzleHttp\Exception\GuzzleException;
use Tiktok\Tiktok\Model\Api\AbstractApiRequest;
use Exception;

/**
 * @internal This class should only be created via a TiktokApiClientBuilder.
 * @see TiktokApiClientBuilder
 */
class TrustSignals extends AbstractApiRequest
{
    /**
     * Endpoint
     */
    public const ENDPOINT = '/plugin/v1.0/partner_insights/update/';

    /**
     * TikTok Base URL
     */
    protected const BASE_URL = 'https://biz-api.tiktok.com';

    /**
     * Send trust signal data (partner data)
     *
     * @param array $data
     * @return bool
     */
    public function sendTrustSignals(array $data): bool
    {
        try {
            $response = $this->sendRequest(
                'POST',
                $data,
                self::ENDPOINT,
                true,
                [],
                null,
                true
            );

            if (!$response || !isset($response['code']) || $response['message'] !== 'OK') {
                $message = $response['message'] ?? 'Failed to send partner data';
                $this->logger->error(__('Error during sending partner data: %1', $message));
                return false;
            } else {
                return true;
            }
        } catch (UserTokenException $e) {
            $this->logger->error('Access TikTok Error: ' . $e->getMessage());
            return false;
        } catch (Exception|GuzzleException $e) {
            $this->logger->error('Error during sending partner data: ' . $e->getMessage());
            return false;
        }
    }
}

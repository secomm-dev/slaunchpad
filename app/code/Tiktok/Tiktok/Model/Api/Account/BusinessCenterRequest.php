<?php
declare(strict_types=1);

namespace Tiktok\Tiktok\Model\Api\Account;

use Tiktok\Tiktok\Model\Api\AbstractApiRequest;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Magento\Framework\Exception\LocalizedException;
use Tiktok\Tiktok\Model\Api\TiktokApiClientBuilder;

/**
 * Business Center Service class.
 * @internal This class should only be created via a TiktokApiClientBuilder.
 * @see TiktokApiClientBuilder
 */
class BusinessCenterRequest extends AbstractApiRequest
{
    /**
     * Define the endpoint for getting business centers
     */
    private const ENDPOINT_GET_BUSINESS_CENTERS = '/open_api/v1.3/bc/get/';

    /**
     * Fetch the list of Business Centers.
     *
     * @param string|null $bcId (optional) Business Center ID
     * @param int $page (optional) Page number (default is 1)
     * @param int $pageSize (optional) Page size (default is 10, max is 50)
     *
     * @return array Response data containing the list of business centers
     * @throws LocalizedException
     * @throws GuzzleException
     */
    public function getBusinessCenters(?string $bcId = null, int $page = 1, int $pageSize = 10): array
    {
        try {
            // Prepare request parameters
            $params = [
                'page' => $page,
                'page_size' => $pageSize];

            // Add the optional Business Center ID if provided
            if ($bcId) {
                $params['bc_id'] = $bcId;
            }

            // Send the GET request to the specified endpoint
            return $this->sendRequest('GET', $params, self::ENDPOINT_GET_BUSINESS_CENTERS);
        } catch (Exception $e) {
            // Log the error and throw an exception
            $this->logger->error('Error fetching business centers: ' . $e->getMessage());
            throw new LocalizedException(__('Error fetching business centers: %1', $e->getMessage()));
        }
    }
}

<?php
declare(strict_types=1);

namespace Tiktok\Tiktok\Model\Api\Catalog;

use Tiktok\Tiktok\Model\Api\AbstractApiRequest;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Magento\Framework\Exception\LocalizedException;

/**
 * @internal This class should only be created via a TiktokApiClientBuilder.
 * @see TiktokApiClientBuilde
 */
class JsonUploadService extends AbstractApiRequest
{
    /**
     * Define the endpoint for product upload
     */
    private const ENDPOINT_PRODUCT_UPLOAD = '/open_api/v1.3/catalog/product/upload/';

    /**
     * Define the endpoint for product upload
     */
    private const ENDPOINT_PRODUCT_DELETE = '/open_api/v1.3/catalog/product/delete/';

    /**
     * Define the maximum number of products per batch
     */
    private const MAX_PRODUCTS_PER_BATCH = 5000;

    /**
     * Define the maximum number of products to delete per one request
     */
    private const MAX_PRODUCTS_DELETE_BATCH = 1000;

    /**
     * Upload products in batch to the TikTok catalog.
     *
     * @param string $bcId Business Center ID
     * @param string $catalogId Catalog ID
     * @param array $products List of products to upload (max 5000)
     * @param string|null $feedId Optional Feed ID
     *
     * @return array Response data containing the feed log ID
     * @throws LocalizedException
     * @throws GuzzleException
     */
    private function sendProductJsonUpload(
        string  $bcId,
        string  $catalogId,
        array   $products,
        ?string $feedId = null
    ): array {
        try {
            // Validate products array size
            if (count($products) > self::MAX_PRODUCTS_PER_BATCH) {
                throw new LocalizedException(__('Cannot upload more than 5000 products at once.'));
            }

            // Prepare the request parameters
            $params = [
                'bc_id' => $bcId,
                'catalog_id' => $catalogId,
                'products' => $products,];

            if ($feedId) {
                $params['feed_id'] = $feedId;
            }
            return $this->sendRequest('POST', $params, self::ENDPOINT_PRODUCT_UPLOAD);
        } catch (Exception $e) {
            $this->logger->error('Error uploading products: ' . $e->getMessage());
            throw new LocalizedException(__('Error uploading products: %1', $e->getMessage()));
        }
    }

    /**
     * Upload products in batches of up to 5000 products each.
     *
     * @param string $bcId Business Center ID
     * @param string $catalogId Catalog ID
     * @param array $products List of products to upload
     * @param string|null $feedId Optional Feed ID
     *
     * @return array List of feed log IDs for all batches
     * @throws GuzzleException
     */
    public function uploadProductsJson(string $bcId, string $catalogId, array $products, ?string $feedId = null): array
    {
        $feedLogIds = [];

        $batches = array_chunk($products, self::MAX_PRODUCTS_PER_BATCH);

        foreach ($batches as $batch) {
            try {
                // Upload each batch and get the response
                $response = $this->sendProductJsonUpload($bcId, $catalogId, $batch, $feedId);

                // Collect feed log IDs for each batch
                if (isset($response['data']['feed_log_id'])) {
                    $feedLogIds[] = $response['data']['feed_log_id'];
                    $this->logger->info('Delta sync feed log ID: ' . $response['data']['feed_log_id']);
                } else {
                    $message = $response['message'] ?? 'Failed uploading products';
                    $this->logger->error(__('Response from Tiktok when upload json: %1', $message));
                }
            } catch (Exception $e) {
                // Log the error for each batch
                $this->logger->error('Error uploading batch: ' . $e->getMessage());
            }
        }

        return $feedLogIds;
    }

    /**
     * Remove products from TikTok by batches
     *
     * @param string $bcId Business Center ID
     * @param string $catalogId Catalog ID
     * @param array $skus List of products skus
     * @param string|null $feedId Optional Feed ID
     *
     * @return array List of feed log IDs for all batches
     * @throws GuzzleException
     * @throws LocalizedException
     */
    public function removeProductsRequest(
        string $bcId,
        string $catalogId,
        array $skus,
        ?string $feedId = null
    ): array {
        $feedLogIds = [];
        $batches = array_chunk($skus, self::MAX_PRODUCTS_DELETE_BATCH);

        foreach ($batches as $batch) {
            try {
                // Prepare the request parameters
                $params = [
                    'bc_id' => $bcId,
                    'catalog_id' => $catalogId,
                    'sku_ids' => $batch
                ];

                if ($feedId) {
                    $params['feed_id'] = $feedId;
                }

                $response = $this->sendRequest('POST', $params, self::ENDPOINT_PRODUCT_DELETE);

                // Collect feed log IDs for each batch
                if (isset($response['data'], $response['data']['feed_log_id'])) {
                    $feedLogIds[] = $response['data']['feed_log_id'];
                    $this->logger->info('Delta sync feed log ID: ' . $response['data']['feed_log_id']);
                } else {
                    $message = $response['message'] ?? 'Failed deleting products';
                    $this->logger->error(__('Response from Tiktok when delete products: %1', $message));
                }
            } catch (Exception $e) {
                // Log the error for each batch
                $this->logger->error('Error removing batch from TikTok: ' . $e->getMessage());
            }
        }

        return $feedLogIds;
    }
}

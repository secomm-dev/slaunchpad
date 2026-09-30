<?php
declare(strict_types=1);

namespace Tiktok\Tiktok\Model\Api\Catalog;

use Tiktok\Tiktok\Model\Api\AbstractApiRequest;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Magento\Framework\Exception\LocalizedException;

/**
 * Catalog API service class.
 *
 * @internal This class should only be created via a TiktokApiClientBuilder.
 * @see TiktokApiClientBuilde
 */
class CatalogService extends AbstractApiRequest
{
    /**
     * Catalog Services Endpoint
     */
    private const ENDPOINT_GET_CATALOGS = '/open_api/v1.3/catalog/get/';
    private const ENDPOINT_CREATE_FEED = '/open_api/v1.3/catalog/feed/create/';
    private const ENDPOINT_UPLOAD_PRODUCTS = '/open_api/v1.3/catalog/product/file/';

    /**
     * Create a catalog feed.
     *
     * @param string $bcId Business Center ID
     * @param string $catalogId Catalog ID
     * @param string $updateMode Update mode (OVERWRITE or INCREMENTAL)
     * @param string $feedName Name of the feed
     *
     * @return array
     * @throws GuzzleException
     * @throws LocalizedException
     */
    public function createFeed(
        string $bcId,
        string $catalogId,
        string $updateMode,
        string $feedName = 'Magento'
    ): array {
        try {

            $updateMode = strtoupper($updateMode);
            if ($updateMode !== 'OVERWRITE' && $updateMode !== 'INCREMENTAL') {
                throw new LocalizedException(
                    __('Invalid update mode: %1', $updateMode, 'expected OVERWRITE or INCREMENTAL')
                );
            }

            $params = array_merge([
                'bc_id' => $bcId,
                'catalog_id' => $catalogId,
                'feed_name' => $feedName,
                'update_mode' => $updateMode,]);

            return $this->sendRequest('POST', $params, self::ENDPOINT_CREATE_FEED);
        } catch (Exception $e) {
            $this->logger->error('Error creating feed: ' . $e->getMessage());
            throw new LocalizedException(__('Error creating feed: %1', $e->getMessage()));
        }
    }

    /**
     * Upload products to the catalog.
     *
     * @param string $bcId Business Center ID
     * @param string $catalogId Catalog ID
     * @param string $fileUrl URL of the product file to upload
     * @param string|null $feedId Optional Feed ID
     * @param string|null $updateMode
     *
     * @throws GuzzleException
     * @throws LocalizedException
     */
    public function uploadCsvExport(
        string $bcId,
        string $catalogId,
        string $fileUrl,
        ?string $feedId,
        ?string $updateMode = null
    ): array {
        try {
            if (!filter_var($fileUrl, FILTER_VALIDATE_URL)) {
                throw new LocalizedException(__('Invalid file URL: %1', $fileUrl));
            }

            $params = [
                'bc_id' => $bcId,
                'catalog_id' => $catalogId,
                'file_url' => $fileUrl,
                'feed_id' => $feedId,];

            if ($updateMode) {
                $params['update_mode'] = $updateMode;
            }

            return $this->sendRequest('POST', $params, self::ENDPOINT_UPLOAD_PRODUCTS);
        } catch (Exception $e) {
            $this->logger->error('Error uploading products: ' . $e->getMessage());
            throw new LocalizedException(__('Error uploading products: %1', $e->getMessage()));
        }
    }
}

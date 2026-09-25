<?php
declare(strict_types=1);

namespace Tiktok\Tiktok\Model\Catalog\Export\Sync;

use DateTime;
use Magento\Framework\FlagManager;

/**
 * Catalog Export Sync Flag Manager Class
 */
class SyncFlagManager
{
    /**
     * Last sync flag code
     */
    public const LAST_SYNC_FLAG_CODE = '_tiktok_last_sync';

    /**
     * Catalog full sync code
     */
    public const CATALOG_FULL_SYNC_CODE = '_tiktok_catalog_full_sync';

    /**
     * @var \Magento\Framework\FlagManager
     */
    private FlagManager $flagManager;

    /**
     * Init dependencies
     *
     * @param \Magento\Framework\FlagManager $flagManager
     */
    public function __construct(FlagManager $flagManager)
    {
        $this->flagManager = $flagManager;
    }

    /**
     * Set Configuration Value
     *
     * @param int $websiteId
     * @return void
     */
    public function updateLastSyncTime(int $websiteId): void
    {
        $currentTime = (new DateTime())->format('Y-m-d H:i:s');
        $this->flagManager->saveFlag($websiteId . self::LAST_SYNC_FLAG_CODE, $currentTime);
    }

    /**
     * Get Configuration Value From Flag By Code
     *
     * @param int $websiteId
     * @return mixed
     */
    public function getLastSyncTime(int $websiteId): mixed
    {
        return $this->flagManager->getFlagData($websiteId . self::LAST_SYNC_FLAG_CODE);
    }

    /**
     * Set catalog full sync flag
     *
     * @param int $websiteId
     * @param bool $status True/False should the sync run.
     *
     * @return void
     */
    public function setCatalogSyncFlag(int $websiteId, bool $status = true): void
    {
        $this->flagManager->saveFlag($websiteId . self::CATALOG_FULL_SYNC_CODE, $status);
    }

    /**
     * Get Configuration Value From Flag By Code
     *
     * @param int $websiteId
     *
     * @return mixed
     */
    public function getCatalogSyncFlag(int $websiteId): mixed
    {
        return $this->flagManager->getFlagData($websiteId . self::CATALOG_FULL_SYNC_CODE);
    }
}

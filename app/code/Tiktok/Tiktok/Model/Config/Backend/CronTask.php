<?php
declare(strict_types=1);

namespace Tiktok\Tiktok\Model\Config\Backend;

class CronTask
{
    /**
     * Cron config template
     */
    public const CRON_STRING_PATH = 'crontab/tiktok_catalog_sync/jobs/tiktok_delta_sync_%d/%s';

    /**
     * Cron callback config
     */
    public const CRON_MODEL = 'Tiktok\Tiktok\Cron\SyncCatalogCron::execute';

    /**
     * Get cron_expr config path
     *
     * @param string $websiteId
     * @return string
     */
    public function getExprConfigPath(string $websiteId): string
    {
        return sprintf(self::CRON_STRING_PATH, $websiteId, 'schedule/cron_expr');
    }

    /**
     * Get cron callback model config path
     *
     * @param string $websiteId
     * @return string
     */
    public function getModelConfigPath(string $websiteId): string
    {
        return sprintf(self::CRON_STRING_PATH, $websiteId, 'run/model');
    }
}

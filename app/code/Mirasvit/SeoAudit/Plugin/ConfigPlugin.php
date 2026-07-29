<?php
/**
 * Mirasvit
 *
 * This source file is subject to the Mirasvit Software License, which is available at https://mirasvit.com/license/.
 * Do not edit or add to this file if you wish to upgrade the to newer versions in the future.
 * If you wish to customize this module for your needs.
 * Please refer to http://www.magentocommerce.com for more information.
 *
 * @category  Mirasvit
 * @package   mirasvit/module-seo
 * @version   2.12.8
 * @copyright Copyright (C) 2026 Mirasvit (https://mirasvit.com/)
 */


declare(strict_types=1);

namespace Mirasvit\SeoAudit\Plugin;

use Magento\Config\Model\Config;
use Mirasvit\SeoAudit\Model\Config\Source\CrawlIntensity;

/**
 * @see \Magento\Config\Model\Config::save()
 */
class ConfigPlugin
{
    /**
     * @param Config   $config
     * @param \Closure $proceed
     * @return mixed
     */
    public function aroundSave(Config $config, \Closure $proceed)
    {
        if ($config->getData('section') === 'seo_audit') {
            $data = $config->getData('groups');

            if (isset($data['performance'])) {
                $fieldsData     = $data['performance']['fields'];
                $customSettings = $data['performance']['groups']['custom_settings']['fields'] ?? false;

                $level = (int)($fieldsData['level']['value'] ?? CrawlIntensity::LEVEL_MEDIUM);

                if ($level === CrawlIntensity::LEVEL_CUSTOM && $customSettings !== false) {
                    $fieldsData['cron_schedule']['value'] = $customSettings['cron_schedule_custom']['value'] ?? '*/30 * * * *';
                } else {
                    $fieldsData['cron_schedule']['value'] = '*/30 * * * *';
                }

                $data['performance']['fields'] = $fieldsData;
                $config->setData('groups', $data);
            }
        }

        return $proceed();
    }
}

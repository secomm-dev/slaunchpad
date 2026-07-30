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



namespace Mirasvit\SeoToolbar\DataProvider\Criteria;

use Mirasvit\SeoToolbar\Api\Data\DataProviderItemInterface;

class SeoAutolinksCriteria extends AbstractCriteria
{
    const LABEL = 'SEO cross-links';

    /**
     * @param string $content
     * @return array
     */
    public function handle($content)
    {
        $autoLinks = $this->getAutoLinks($content);

        if (!$autoLinks) {
            return $this->getItem(self::LABEL, DataProviderItemInterface::STATUS_NONE, __('No cross-links detected'), '');
        } else {
            return $this->getItem(
                self::LABEL,
                DataProviderItemInterface::STATUS_SUCCESS,
                __('Cross-links successfully applied'),
                implode(PHP_EOL, $autoLinks)
            );
        }
    }

    /**
     * @param string $content
     * @return array
     */
    private function getAutoLinks($content)
    {
        $result = [];
        $counter = [];

        $matches = [];
        preg_match_all("/<a class='mst_seo_autolink(.*)[^>]+>(.*)<\/a>/iU", $content, $matches);

        if (empty($matches[0])) {
            return [];
        } else {
            foreach ($matches[0] as $autoLinkTag) {
                preg_match('/>(.*)</', $autoLinkTag, $autoLinkTitle);
                preg_match_all('/(data-page-limit|data-link-limit)="([^"]*)"/i', $autoLinkTag, $autoLinkData);
                $tmpData = [];

                foreach ($autoLinkData[0] as $data) {
                    $data = str_replace('data-page-limit', 'Page limit', $data);
                    $data = str_replace('data-link-limit', 'Links limit', $data);
                    $tmpData[] = $data;
                }

                $key = $autoLinkTitle[1] ?? '';
                if (!isset($counter[$key])) {
                    $counter[$key] = 1;
                } else {
                    $counter[$key] += 1;
                }

                $result[$key] = '"' . $key . '" title : ' . implode(',', $tmpData);
            }
        }

        foreach ($counter as $key => $qty) {
            if ($qty > 1 && isset($result[$key])) {
                $result[$key] .= ', ' . $qty . ' replacements';
            }
        }

        return $result;
    }
}

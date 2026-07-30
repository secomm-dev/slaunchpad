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



namespace Mirasvit\SeoSitemap\Block\Map;

use Mirasvit\SeoSitemap\Model\Pager\Collection as PagerCollection;

class Pager extends \Magento\Theme\Block\Html\Pager
{
    /**
     * @param PagerCollection $collection
     *
     * @return $this
     */
    public function setCollection($collection)
    {
        $collection->setCurPage($this->getCurrentPage());
        if ((int)$this->getLimit()) {
            $collection->setPageSize($this->getLimit());
        }
        parent::setCollection($collection);

        return $this;
    }

    /**
     * @return string
     */
    public function getPreviousPageUrl()
    {
        return $this->getPageUrl((string)($this->getCollection()->getCurPage() - 1));
    }

    /**
     * @return string
     */
    public function getNextPageUrl()
    {
        return $this->getPageUrl((string)($this->getCollection()->getCurPage() + 1));
    }

    /**
     * @param array $params
     * @return string
     */
    public function getPagerUrl($params = [])
    {
        $url = parse_url((string)$_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '';

        if (count($params) > 0 && $params['p'] > 1) {
            $query = http_build_query($params);
            $url .= '?' . $query;
        }

        return $url;
    }
}

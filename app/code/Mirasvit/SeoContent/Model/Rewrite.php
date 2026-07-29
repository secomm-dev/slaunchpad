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



namespace Mirasvit\SeoContent\Model;

use Mirasvit\SeoContent\Api\Data\RewriteInterface;

class Rewrite extends Content implements RewriteInterface
{
    protected function _construct()
    {
        $this->_init(ResourceModel\Rewrite::class);
    }

    /**
     * @param string $value
     * @return \Magento\Framework\Model\AbstractModel|\Mirasvit\SeoContent\Api\Data\ContentInterface|RewriteInterface
     */
    public function setUrl($value)
    {
        return $this->setData(self::URL, $value);
    }

    /**
     * @return Rewrite|string
     */
    public function getUrl()
    {
        return $this->getData(self::URL);
    }

    /**
     * @param bool $value
     * @return \Magento\Framework\Model\AbstractModel|\Mirasvit\SeoContent\Api\Data\ContentInterface|RewriteInterface
     */
    public function setIsActive($value)
    {
        return $this->setData(self::IS_ACTIVE, $value);
    }

    /**
     * @return bool|null
     */
    public function getIsActive()
    {
        return $this->getData(self::IS_ACTIVE);
    }

    /**
     * @param string $value
     * @return \Magento\Framework\Model\AbstractModel|\Mirasvit\SeoContent\Api\Data\ContentInterface|RewriteInterface
     */
    public function setSortOrder($value)
    {
        return $this->setData(self::SORT_ORDER, $value);
    }

    /**
     * @return Rewrite|string
     */
    public function getSortOrder()
    {
        return $this->getData(self::SORT_ORDER);
    }

    /**
     * @param array $value
     * @return \Magento\Framework\Model\AbstractModel|\Mirasvit\SeoContent\Api\Data\ContentInterface|RewriteInterface
     */
    public function setStoreIds(array $value)
    {
        return $this->setData(self::STORE_IDS, implode(',', $value));
    }

    /**
     * @return array
     */
    public function getStoreIds()
    {
        return explode(',', $this->getData(self::STORE_IDS));
    }

    public function setAddToSitemap(int $value): RewriteInterface
    {
        return $this->setData(RewriteInterface::ADD_TO_SITEMAP, $value);
    }

    public function getAddToSitemap(): int
    {
        return (int)$this->getData(RewriteInterface::ADD_TO_SITEMAP);
    }

    public function setUseInBreadcrumbs(bool $value): RewriteInterface
    {
        return $this->setData(RewriteInterface::USE_IN_BREADCRUMBS, $value ? 1 : 0);
    }

    public function getUseInBreadcrumbs(): bool
    {
        return (bool)$this->getData(RewriteInterface::USE_IN_BREADCRUMBS);
    }
}
